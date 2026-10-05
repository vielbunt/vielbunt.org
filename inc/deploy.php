<?php
/**
 * Theme-Updates direkt aus GitHub.
 * (Gleiche Datei in csd-darmstadt.de und vielbunt.org, bitte in beiden Repos gleich halten.)
 *
 * Ablauf:
 *  1. Push auf main: die GitHub Action prüft das Theme, baut theme.zip und
 *     release.json und legt damit ein Release an.
 *  2. Danach ruft sie POST /wp-json/<namespace>/deploy mit dem Deploy-Token auf.
 *  3. WordPress lädt das Release und installiert es über den ganz normalen
 *     Theme-Updater, in den bestehenden Theme-Ordner. Inhalte, Menüs und
 *     Startseiten-Einstellungen bleiben unangetastet.
 *
 * Fällt der Webhook mal aus, sieht WordPress das Update trotzdem unter
 * Dashboard > Aktualisierungen und spielt es mit dem automatischen
 * Hintergrund-Update ein (das schalten wir für dieses Theme fest an).
 *
 * Übersicht und Token: Design > Theme-Updates.
 *
 * @package vielbunt-themes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Vielbunt_Theme_Deploy' ) ) {

	class Vielbunt_Theme_Deploy {

		private $repo;
		private $ns;
		private $prefix;
		private $slug;
		private $once;

		/**
		 * @param array $args repo (owner/name), namespace (REST, z. B. csd/v1), prefix (für Optionsnamen),
		 *                    once (optional): einmalige Schritte, id => array( Beschreibung, callable ).
		 */
		public function __construct( $args ) {
			$this->repo   = $args['repo'];
			$this->ns     = $args['namespace'];
			$this->prefix = $args['prefix'];
			$this->slug   = get_stylesheet();
			$this->once   = isset( $args['once'] ) ? (array) $args['once'] : array();

			add_filter( 'pre_set_site_transient_update_themes', array( $this, 'inject_update' ) );
			add_filter( 'auto_update_theme', array( $this, 'auto_update' ), 10, 2 );
			add_filter( 'upgrader_source_selection', array( $this, 'fix_folder' ), 10, 4 );
			add_action( 'rest_api_init', array( $this, 'register_route' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_post_' . $this->prefix . '_deploy', array( $this, 'admin_action' ) );
			add_action( 'init', array( $this, 'run_once' ), 98 );
			add_action( 'init', array( $this, 'purge_after_update' ), 99 );

			// Website-Editor speichert an WP-Optimize vorbei, siehe purge_home()/purge_pages()
			add_action( 'update_option_' . $this->prefix . '_frontpage', array( $this, 'purge_home' ) );
			foreach ( $this->site_types() as $type ) {
				add_action( 'save_post_' . $type, array( $this, 'purge_pages' ) );
			}
			add_action( 'deleted_post', array( $this, 'purge_pages_on_delete' ), 10, 2 );
			add_action( 'update_option_blogname', array( $this, 'purge_pages' ) );
			add_action( 'update_option_blogdescription', array( $this, 'purge_pages' ) );
		}

		/* ---------- Einmalige Schritte ---------- */

		/* Für Dinge, die nach einem Update genau einmal auf dem Server passieren
		   sollen (z. B. ein Plugin abschalten, das das Theme jetzt selbst
		   erledigt). Jeder Schritt wird VOR dem Ausführen als erledigt markiert,
		   damit ein Fehler nie bei jedem Seitenaufruf neu knallt. Ergebnis steht
		   unter Design > Theme-Updates. */
		public function run_once() {
			if ( empty( $this->once ) ) {
				return;
			}
			$key  = $this->prefix . '_once_log';
			$done = get_option( $key, array() );
			$done = is_array( $done ) ? $done : array();
			$ran  = false;
			foreach ( $this->once as $id => $step ) {
				if ( isset( $done[ $id ] ) || ! is_array( $step ) || ! is_callable( $step[1] ) ) {
					continue;
				}
				$done[ $id ] = array( 'time' => time(), 'label' => (string) $step[0], 'result' => 'läuft' );
				update_option( $key, $done, false );
				try {
					$result = call_user_func( $step[1] );
				} catch ( \Throwable $e ) {
					$result = 'Fehler: ' . $e->getMessage();
				}
				$done[ $id ]['result'] = is_string( $result ) ? $result : 'erledigt';
				update_option( $key, $done, false );
				$ran = true;
			}
			if ( $ran ) {
				$this->purge_caches();
			}
		}


		/* ---------- Release-Infos aus GitHub ---------- */

		/* Wir lesen bewusst nicht die GitHub-API (60 Anfragen pro Stunde und IP,
		   auf Shared Hosting schnell aufgebraucht), sondern die release.json, die
		   die Action an jedes Release hängt. */
		public function release( $fresh = false ) {
			$key = $this->prefix . '_deploy_release';
			if ( ! $fresh ) {
				$cached = get_site_transient( $key );
				if ( is_array( $cached ) ) {
					return $cached;
				}
			}

			$res = wp_remote_get(
				'https://github.com/' . $this->repo . '/releases/latest/download/release.json',
				array( 'timeout' => 15 )
			);
			$data = array();
			if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
				$json = json_decode( wp_remote_retrieve_body( $res ), true );
				$base = 'https://github.com/' . $this->repo . '/releases/download/';
				// Nur Pakete aus genau unserem Repo annehmen.
				if ( is_array( $json ) && ! empty( $json['version'] ) && ! empty( $json['package'] )
					&& 0 === strpos( (string) $json['package'], $base ) ) {
					$data = array(
						'version' => (string) $json['version'],
						'package' => (string) $json['package'],
						'commit'  => isset( $json['commit'] ) ? (string) $json['commit'] : '',
						'message' => isset( $json['message'] ) ? (string) $json['message'] : '',
					);
				}
			}

			// Fehlschläge nur kurz merken, damit wir GitHub nicht bei jedem Seitenaufruf fragen.
			set_site_transient( $key, $data, $data ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
			return $data;
		}

		/* Version direkt aus der style.css lesen, wp_get_theme() cached zu aggressiv */
		public function installed_version() {
			$file = get_theme_root( $this->slug ) . '/' . $this->slug . '/style.css';
			$data = get_file_data( $file, array( 'Version' => 'Version' ) );
			return (string) $data['Version'];
		}

		/* ---------- Anbindung an den WordPress-Updater ---------- */

		public function inject_update( $transient ) {
			if ( ! is_object( $transient ) ) {
				return $transient;
			}
			$rel = $this->release();
			if ( empty( $rel ) ) {
				return $transient;
			}
			$item = array(
				'theme'        => $this->slug,
				'new_version'  => $rel['version'],
				'url'          => 'https://github.com/' . $this->repo,
				'package'      => $rel['package'],
				'requires'     => '',
				'requires_php' => '',
			);
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			if ( version_compare( $rel['version'], $this->installed_version(), '>' ) ) {
				$transient->response[ $this->slug ] = $item;
				unset( $transient->no_update[ $this->slug ] );
			} else {
				$transient->no_update[ $this->slug ] = $item;
				unset( $transient->response[ $this->slug ] );
			}
			return $transient;
		}

		public function auto_update( $update, $item ) {
			if ( is_object( $item ) && isset( $item->theme ) && $item->theme === $this->slug ) {
				return true;
			}
			return $update;
		}

		/* Das ZIP enthält schon den richtigen Ordnernamen. Falls nicht (z. B. ein
		   ZIP von Hand gebaut), benennen wir ihn um, damit nie ein zweites Theme
		   neben dem aktiven landet. */
		public function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
			if ( empty( $hook_extra['theme'] ) || $hook_extra['theme'] !== $this->slug ) {
				return $source;
			}
			$wanted = trailingslashit( $remote_source ) . $this->slug . '/';
			if ( trailingslashit( $source ) === $wanted ) {
				return $source;
			}
			global $wp_filesystem;
			if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
				return $wanted;
			}
			return new WP_Error( 'deploy_folder', 'Der Theme-Ordner im Paket konnte nicht umbenannt werden.' );
		}

		/* ---------- Caches leeren ---------- */

		/* Seiten-Caches (WP-Optimize & Co.) wissen nichts von Theme-Updates und
		   liefern sonst stundenlang die alte Seite aus. Läuft beim ersten Aufruf,
		   der WordPress wirklich lädt, nachdem sich die Theme-Version geändert hat
		   (z. B. der Live-Check der GitHub Action). Absichtlich hier und nicht
		   direkt im Deploy: während des Updates läuft noch der alte Code. */
		public function purge_after_update() {
			$key     = $this->prefix . '_deploy_seen_version';
			$version = (string) wp_get_theme( $this->slug )->get( 'Version' );
			if ( get_option( $key ) === $version ) {
				return;
			}
			update_option( $key, $version, true );
			$this->purge_caches();
		}

		public function purge_caches() {
			$calls = array(
				function () {
					if ( function_exists( 'WP_Optimize' ) && method_exists( WP_Optimize(), 'get_page_cache' ) ) {
						$cache = WP_Optimize()->get_page_cache();
						if ( $cache && method_exists( $cache, 'purge' ) ) {
							$cache->purge();
						}
					}
				},
				function () {
					if ( function_exists( 'wpo_cache_flush' ) ) {
						wpo_cache_flush();
					}
				},
				function () {
					if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
						autoptimizeCache::clearall();
					}
				},
				function () {
					if ( function_exists( 'rocket_clean_domain' ) ) {
						rocket_clean_domain();
					}
					if ( function_exists( 'w3tc_flush_all' ) ) {
						w3tc_flush_all();
					}
					if ( function_exists( 'wp_cache_clear_cache' ) ) {
						wp_cache_clear_cache();
					}
					do_action( 'litespeed_purge_all' );
				},
				function () {
					wp_cache_flush();
				},
			);
			// Ein kaputtes Cache-Plugin soll nie die Seite mitreißen
			foreach ( $calls as $call ) {
				try {
					$call();
				} catch ( \Throwable $e ) {
					continue;
				}
			}
		}

		/* ---------- Speichern im Website-Editor ---------- */

		/* WP-Optimize leert den Seitencache nur bei Beiträgen und Seiten. Hero und
		   Schnellzugriff (Option), Templates, Menüs und globale Stile werden aber
		   im Website-Editor gespeichert, davon bekommt WP-Optimize nix mit. Ohne
		   das hier stünde die alte Startseite bis zum Ablauf des Caches online. */
		private function site_types() {
			return array( 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles' );
		}

		/* Startseiten-Option geändert: nur die Startseite neu */
		public function purge_home() {
			try {
				if ( class_exists( 'WPO_Page_Cache' ) && is_callable( array( 'WPO_Page_Cache', 'delete_homepage_cache' ) ) ) {
					WPO_Page_Cache::delete_homepage_cache();
				}
			} catch ( \Throwable $e ) {
				return;
			}
		}

		/* Template, Menü oder Stile geändert: betrifft jede Seite. Nur der
		   Seitencache, Autoptimize bleibt (das CSS/JS ändert sich dabei nicht). */
		public function purge_pages() {
			try {
				if ( function_exists( 'WP_Optimize' ) && method_exists( WP_Optimize(), 'get_page_cache' ) ) {
					$cache = WP_Optimize()->get_page_cache();
					if ( $cache && method_exists( $cache, 'purge' ) ) {
						$cache->purge();
					}
				}
			} catch ( \Throwable $e ) {
				return;
			}
		}

		/* "Zurücksetzen" im Website-Editor löscht den Template-Post nur, da kommt kein save_post */
		public function purge_pages_on_delete( $post_id, $post = null ) {
			if ( $post instanceof WP_Post && in_array( $post->post_type, $this->site_types(), true ) ) {
				$this->purge_pages();
			}
		}

		/* ---------- Deploy ---------- */

		public function token() {
			$const = strtoupper( $this->prefix ) . '_DEPLOY_TOKEN';
			if ( defined( $const ) && '' !== (string) constant( $const ) ) {
				return (string) constant( $const );
			}
			return (string) get_option( $this->prefix . '_deploy_token', '' );
		}

		public function deploy( $trigger ) {
			$lock = $this->prefix . '_deploy_lock';
			if ( get_transient( $lock ) ) {
				return $this->finish( $trigger, false, 'läuft schon', $this->installed_version(), '', array( 'Ein anderes Update läuft gerade.' ) );
			}
			set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$from = $this->installed_version();
			$rel  = $this->release( true );
			if ( empty( $rel ) ) {
				return $this->finish( $trigger, false, 'kein Release', $from, '', array( 'Auf GitHub wurde kein gültiges Release gefunden.' ) );
			}
			if ( ! version_compare( $rel['version'], $from, '>' ) ) {
				return $this->finish( $trigger, true, 'schon aktuell', $from, $rel['version'], array() );
			}

			$method = get_filesystem_method( array(), get_theme_root( $this->slug ) );
			if ( 'direct' !== $method || ! WP_Filesystem() ) {
				return $this->finish( $trigger, false, 'kein Schreibzugriff', $from, $rel['version'], array( 'WordPress darf das Theme nicht selbst schreiben (Dateisystem-Methode: ' . $method . ').' ) );
			}

			// Update-Liste neu aufbauen, inject_update() hängt unser Release dran.
			$current = get_site_transient( 'update_themes' );
			if ( ! is_object( $current ) ) {
				$current = new stdClass();
			}
			$current->last_checked = time();
			set_site_transient( 'update_themes', $current );

			$skin     = new WP_Ajax_Upgrader_Skin();
			$upgrader = new Theme_Upgrader( $skin );
			$result   = $upgrader->upgrade( $this->slug );

			wp_clean_themes_cache();
			$to       = $this->installed_version();
			$messages = array();
			foreach ( $skin->get_upgrade_messages() as $m ) {
				$messages[] = wp_strip_all_tags( is_string( $m ) ? $m : wp_json_encode( $m ) );
			}
			if ( is_wp_error( $result ) ) {
				$messages[] = $result->get_error_message();
			}
			if ( $skin->get_errors()->has_errors() ) {
				$messages = array_merge( $messages, $skin->get_errors()->get_error_messages() );
			}
			$ok = ( true === $result || ( ! is_wp_error( $result ) && $result ) ) && $to === $rel['version'];

			return $this->finish( $trigger, $ok, $ok ? 'aktualisiert' : 'fehlgeschlagen', $from, $to, $messages );
		}

		private function finish( $trigger, $ok, $status, $from, $to, $messages ) {
			$entry = array(
				'time'     => time(),
				'trigger'  => $trigger,
				'ok'       => (bool) $ok,
				'status'   => $status,
				'from'     => $from,
				'to'       => $to,
				'messages' => array_values( array_slice( array_filter( (array) $messages ), -15 ) ),
			);
			$log = get_option( $this->prefix . '_deploy_log', array() );
			$log = is_array( $log ) ? $log : array();
			array_unshift( $log, $entry );
			update_option( $this->prefix . '_deploy_log', array_slice( $log, 0, 10 ), false );
			if ( 'läuft schon' !== $status ) {
				delete_transient( $this->prefix . '_deploy_lock' );
			}
			return $entry;
		}

		/* ---------- REST-Webhook für die GitHub Action ---------- */

		public function register_route() {
			register_rest_route(
				$this->ns,
				'/deploy',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_deploy' ),
					'permission_callback' => array( $this, 'rest_permission' ),
				)
			);
		}

		public function rest_permission( WP_REST_Request $request ) {
			$expected = $this->token();
			$given    = (string) $request->get_header( 'X-Deploy-Token' );
			if ( '' === $given ) {
				$given = (string) $request->get_param( 'token' );
			}
			return '' !== $expected && '' !== $given && hash_equals( $expected, $given );
		}

		public function rest_deploy() {
			$entry = $this->deploy( 'github' );
			return new WP_REST_Response( $entry, $entry['ok'] ? 200 : 500 );
		}

		/* ---------- Seite unter Design > Theme-Updates ---------- */

		public function admin_menu() {
			add_theme_page( 'Theme-Updates', 'Theme-Updates', 'manage_options', $this->prefix . '-deploy', array( $this, 'admin_page' ) );
		}

		public function admin_action() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Keine Berechtigung.' );
			}
			check_admin_referer( $this->prefix . '_deploy' );
			$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
			if ( 'token' === $do ) {
				update_option( $this->prefix . '_deploy_token', wp_generate_password( 40, false, false ), false );
			} elseif ( 'update' === $do ) {
				$this->deploy( 'admin' );
			} elseif ( 'check' === $do ) {
				$this->release( true );
			}
			wp_safe_redirect( admin_url( 'themes.php?page=' . $this->prefix . '-deploy' ) );
			exit;
		}

		private function button( $do, $label, $primary = false ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
			wp_nonce_field( $this->prefix . '_deploy' );
			echo '<input type="hidden" name="action" value="' . esc_attr( $this->prefix . '_deploy' ) . '">';
			echo '<input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
			submit_button( $label, $primary ? 'primary' : 'secondary', 'submit', false );
			echo '</form>';
		}

		public function admin_page() {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$rel    = $this->release();
			$inst   = $this->installed_version();
			$const  = strtoupper( $this->prefix ) . '_DEPLOY_TOKEN';
			$token  = $this->token();
			$method = get_filesystem_method( array(), get_theme_root( $this->slug ) );
			$log    = get_option( $this->prefix . '_deploy_log', array() );
			$url    = rest_url( $this->ns . '/deploy' );
			?>
			<div class="wrap">
				<h1>Theme-Updates</h1>
				<p>Jeder Push auf <code>main</code> in <a href="<?php echo esc_url( 'https://github.com/' . $this->repo ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $this->repo ); ?></a> wird geprüft, als Release gebaut und hier automatisch eingespielt.</p>

				<table class="widefat striped" style="max-width:820px">
					<tbody>
						<tr><th style="width:220px">Theme-Ordner</th><td><code><?php echo esc_html( $this->slug ); ?></code></td></tr>
						<tr><th>Installierte Version</th><td><strong><?php echo esc_html( $inst ); ?></strong></td></tr>
						<tr><th>Neuestes Release</th><td>
							<?php if ( $rel ) : ?>
								<strong><?php echo esc_html( $rel['version'] ); ?></strong>
								<?php if ( $rel['message'] ) : ?> &middot; <?php echo esc_html( $rel['message'] ); ?><?php endif; ?>
								<?php echo version_compare( $rel['version'], $inst, '>' ) ? ' <span style="color:#b32d2e">(Update verfügbar)</span>' : ' <span style="color:#008a20">(aktuell)</span>'; ?>
							<?php else : ?>
								<em>GitHub gerade nicht erreichbar oder noch kein Release</em>
							<?php endif; ?>
						</td></tr>
						<tr><th>Schreibzugriff</th><td><?php echo 'direct' === $method ? '<span style="color:#008a20">ok</span>' : '<span style="color:#b32d2e">' . esc_html( $method ) . ', automatische Updates gehen so nicht</span>'; ?></td></tr>
						<tr><th>Webhook</th><td><code><?php echo esc_html( $url ); ?></code></td></tr>
						<tr><th>Deploy-Token</th><td>
							<?php if ( defined( $const ) && '' !== (string) constant( $const ) ) : ?>
								in der <code>wp-config.php</code> gesetzt (<code><?php echo esc_html( $const ); ?></code>)
							<?php elseif ( $token ) : ?>
								<input type="text" readonly value="<?php echo esc_attr( $token ); ?>" style="width:100%;font-family:monospace" onclick="this.select()">
								<p class="description">Gehört in GitHub unter Settings &gt; Secrets and variables &gt; Actions als <code>DEPLOY_TOKEN</code>.</p>
							<?php else : ?>
								<em>noch keins, ohne Token gibt es nur das automatische Update zweimal am Tag</em>
							<?php endif; ?>
						</td></tr>
					</tbody>
				</table>

				<p style="margin-top:16px">
					<?php
					$this->button( 'update', 'Jetzt aktualisieren', true );
					$this->button( 'check', 'Auf GitHub nachsehen' );
					if ( ! defined( $const ) ) {
						$this->button( 'token', $token ? 'Neues Token erzeugen' : 'Token erzeugen' );
					}
					?>
				</p>

				<?php $once = get_option( $this->prefix . '_once_log', array() ); ?>
				<?php if ( ! empty( $once ) && is_array( $once ) ) : ?>
					<h2>Einmalige Schritte</h2>
					<table class="widefat striped" style="max-width:820px">
						<thead><tr><th>Zeit</th><th>Schritt</th><th>Ergebnis</th></tr></thead>
						<tbody>
						<?php foreach ( $once as $e ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( 'd.m.Y H:i', $e['time'] ) ); ?></td>
								<td><?php echo esc_html( $e['label'] ); ?></td>
								<td><?php echo esc_html( $e['result'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<h2>Letzte Läufe</h2>
				<?php if ( empty( $log ) ) : ?>
					<p>Noch keine.</p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:820px">
						<thead><tr><th>Zeit</th><th>Auslöser</th><th>Ergebnis</th><th>Version</th></tr></thead>
						<tbody>
						<?php foreach ( $log as $e ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( 'd.m.Y H:i', $e['time'] ) ); ?></td>
								<td><?php echo esc_html( $e['trigger'] ); ?></td>
								<td style="color:<?php echo $e['ok'] ? '#008a20' : '#b32d2e'; ?>">
									<?php echo esc_html( $e['status'] ); ?>
									<?php if ( ! empty( $e['messages'] ) && ! $e['ok'] ) : ?>
										<br><small><?php echo esc_html( implode( ' / ', $e['messages'] ) ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $e['from'] . ( $e['to'] && $e['to'] !== $e['from'] ? ' > ' . $e['to'] : '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
			<?php
		}
	}
}
