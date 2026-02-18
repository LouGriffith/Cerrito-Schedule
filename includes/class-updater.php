<?php
/**
 * Cerrito_Schedule_Updater
 *
 * Hooks into WordPress's native plugin update pipeline and checks
 * GitHub Releases for newer versions. No third-party libraries required.
 *
 * Usage: new Cerrito_Schedule_Updater( __FILE__ );
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Cerrito_Schedule_Updater {

    private string $plugin_slug;
    private string $plugin_file;
    private string $github_user = 'LouGriffith';
    private string $github_repo = 'Cerrito-Schedule';
    private array  $plugin_data  = [];
    private mixed  $github_response = null;

    public function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename( $plugin_file );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install',                 [ $this, 'after_install' ], 10, 3 );
        add_action( 'upgrader_process_complete',             [ $this, 'purge_cache' ], 10, 2 );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function get_plugin_data(): array {
        if ( empty( $this->plugin_data ) ) {
            $this->plugin_data = get_plugin_data( $this->plugin_file );
        }
        return $this->plugin_data;
    }

    /**
     * Fetch the latest release from the GitHub API.
     * Result is cached in a transient for 6 hours.
     */
    private function get_github_release(): object|false {
        if ( $this->github_response !== null ) {
            return $this->github_response;
        }

        $cache_key = 'cerrito_schedule_github_release';
        $cached    = get_transient( $cache_key );

        if ( $cached !== false ) {
            $this->github_response = $cached;
            return $cached;
        }

        $url      = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
        $response = wp_remote_get( $url, [
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );

        if ( empty( $body ) || empty( $body->tag_name ) ) {
            return false;
        }

        $this->github_response = $body;
        set_transient( $cache_key, $body, 6 * HOUR_IN_SECONDS );

        return $body;
    }

    /**
     * Return the download URL for the release zip.
     * Prefers an explicit .zip asset; falls back to GitHub's auto-generated source zip.
     */
    private function get_download_url( object $release ): string {
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( strtolower( pathinfo( $asset->name, PATHINFO_EXTENSION ) ) === 'zip' ) {
                    return $asset->browser_download_url;
                }
            }
        }
        return "https://github.com/{$this->github_user}/{$this->github_repo}/archive/refs/tags/{$release->tag_name}.zip";
    }

    /**
     * Strip a leading "v" or "V" from a GitHub tag to get a plain version string.
     * e.g. "v6.2" → "6.2"
     */
    private function normalize_version( string $tag ): string {
        return ltrim( $tag, 'vV' );
    }

    // ── WordPress hooks ───────────────────────────────────────────────────────

    /**
     * Inject our plugin into WordPress's update transient when a newer
     * version is available on GitHub.
     */
    public function check_for_update( object $transient ): object {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_github_release();
        if ( ! $release ) {
            return $transient;
        }

        $plugin_data     = $this->get_plugin_data();
        $current_version = $plugin_data['Version'];
        $latest_version  = $this->normalize_version( $release->tag_name );

        if ( version_compare( $latest_version, $current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_slug,
                'new_version' => $latest_version,
                'url'         => "https://github.com/{$this->github_user}/{$this->github_repo}",
                'package'     => $this->get_download_url( $release ),
                'icons'       => [],
                'banners'     => [],
            ];
        }

        return $transient;
    }

    /**
     * Populate the "View details" modal in the plugins list.
     */
    public function plugin_info( mixed $result, string $action, object $args ): mixed {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $release     = $this->get_github_release();
        $plugin_data = $this->get_plugin_data();

        if ( ! $release ) {
            return $result;
        }

        return (object) [
            'name'          => $plugin_data['Name'],
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => $this->normalize_version( $release->tag_name ),
            'author'        => $plugin_data['Author'],
            'homepage'      => $plugin_data['PluginURI'],
            'download_link' => $this->get_download_url( $release ),
            'sections'      => [
                'description' => $plugin_data['Description'],
                'changelog'   => nl2br( isset( $release->body ) ? esc_html( $release->body ) : 'See GitHub for release notes.' ),
            ],
            'last_updated'  => isset( $release->published_at ) ? date( 'Y-m-d', strtotime( $release->published_at ) ) : '',
            'tested'        => get_bloginfo( 'version' ),
            'requires'      => '5.0',
        ];
    }

    /**
     * After install: rename the extracted GitHub zip folder to match
     * the expected plugin directory, then re-activate the plugin.
     */
    public function after_install( mixed $response, array $hook_extra, array $result ): mixed {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $response;
        }

        $install_directory = plugin_dir_path( $this->plugin_file );
        $wp_filesystem->move( $result['destination'], $install_directory );
        $result['destination'] = $install_directory;

        activate_plugin( $this->plugin_slug );

        return $result;
    }

    /**
     * Delete the cached release after an upgrade so the next check
     * fetches fresh data from GitHub.
     */
    public function purge_cache( object $upgrader, array $options ): void {
        if (
            $options['action'] === 'update' &&
            $options['type']   === 'plugin'  &&
            isset( $options['plugins'] )
        ) {
            foreach ( $options['plugins'] as $plugin ) {
                if ( $plugin === $this->plugin_slug ) {
                    delete_transient( 'cerrito_schedule_github_release' );
                    break;
                }
            }
        }
    }
}
