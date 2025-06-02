<div class="wrap">
    <h1><?php esc_html_e( 'Plugin Optimizer', 'wp-autoplugin' ); ?></h1>

    <div id="plugin-optimizer-ui-container">
        <p>
            <label for="optimize-plugin-select"><?php esc_html_e( 'Select Plugin to Optimize:', 'wp-autoplugin' ); ?></label>
            <select id="optimize-plugin-select" name="optimize_plugin_select">
                <option value="" disabled selected><?php esc_html_e( '-- Select Plugin --', 'wp-autoplugin' ); ?></option>
                <?php
                // TODO: Populate this list with installed plugins that can be optimized.
                // For now, adding a dummy option.
                if ( ! function_exists( 'get_plugins' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $all_plugins = get_plugins();
                foreach ( $all_plugins as $plugin_file => $plugin_data ) {
                    // Simple check to exclude active plugins or plugins from wordpress.org for safety in a real scenario.
                    // For this task, we'll list them all but a real implementation would need more checks.
                    printf(
                        '<option value="%s">%s</option>',
                        esc_attr( $plugin_file ),
                        esc_html( $plugin_data['Name'] )
                    );
                }
                ?>
            </select>
        </p>

        <p>
            <button type="button" id="get-optimization-plan-btn" class="button button-primary">
                <?php esc_html_e( 'Get Optimization Plan', 'wp-autoplugin' ); ?>
            </button>
        </p>

        <div id="optimization-plan-display-container" style="display: none;">
            <h2><?php esc_html_e( 'Optimization Plan', 'wp-autoplugin' ); ?></h2>
            <textarea id="optimization-plan-display" rows="15" cols="100" readonly style="width:100%;"></textarea>
        </div>

        <p id="apply-optimization-btn-container" style="display: none;">
            <button type="button" id="apply-optimization-btn" class="button button-secondary">
                <?php esc_html_e( 'Apply Optimization', 'wp-autoplugin' ); ?>
            </button>
        </p>

        <div id="revert-info-container" style="display: none; margin-top: 20px; padding: 10px; border: 1px solid #ccd0d4; background-color: #f6f7f7;">
            <h3><?php esc_html_e( 'Backup Information', 'wp-autoplugin' ); ?></h3>
            <p id="backup-timestamp-display"></p>
            <button type="button" id="revert-plugin-btn" class="button">
                <?php esc_html_e( 'Revert to Original Backup', 'wp-autoplugin' ); ?>
            </button>
        </div>

        <div id="optimizer-messages" style="margin-top: 15px;">
            <!-- Messages will be displayed here -->
        </div>
    </div>
</div>
