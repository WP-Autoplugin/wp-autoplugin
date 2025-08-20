<!-- Model Selection Modal -->
<div id="model-selection-modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
	<div style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 500px; max-width: 90%; border-radius: 4px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
			<h3 style="margin: 0;"><?php esc_html_e( 'Select Models', 'wp-autoplugin' ); ?></h3>
			<span id="close-modal" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
		</div>
		
		<div style="margin-bottom: 15px;">
			<label for="modal-default-model" style="display: block; font-weight: bold; margin-bottom: 5px;"><?php esc_html_e( 'Default Model:', 'wp-autoplugin' ); ?></label>
			<select id="modal-default-model" style="width: 100%;">
				<?php
				foreach ( self::get_models() as $provider => $models ) {
					echo '<optgroup label="' . esc_attr( $provider ) . '">';
					foreach ( $models as $model_id => $model_name ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $model_id ),
							selected( get_option( 'wp_autoplugin_model' ), $model_id, false ),
							esc_html( $model_name )
						);
					}
					echo '</optgroup>';
				}

				$custom_models = get_option( 'wp_autoplugin_custom_models', [] );
				if ( ! empty( $custom_models ) ) {
					echo '<optgroup label="' . esc_attr__( 'Custom Models', 'wp-autoplugin' ) . '">';
					foreach ( $custom_models as $custom_model ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $custom_model['name'] ),
							selected( get_option( 'wp_autoplugin_model' ), $custom_model['name'], false ),
							esc_html( $custom_model['name'] )
						);
					}
					echo '</optgroup>';
				}
				?>
			</select>
		</div>

		<div style="margin-bottom: 15px;">
			<label for="modal-planner-model" style="display: block; font-weight: bold; margin-bottom: 5px;"><?php esc_html_e( 'Planner Model:', 'wp-autoplugin' ); ?></label>
			<select id="modal-planner-model" style="width: 100%;">
				<option value="" <?php selected( get_option( 'wp_autoplugin_planner_model' ), '', false ); ?>><?php esc_html_e( 'Use Default Model', 'wp-autoplugin' ); ?></option>
				<?php
				foreach ( self::get_models() as $provider => $models ) {
					echo '<optgroup label="' . esc_attr( $provider ) . '">';
					foreach ( $models as $model_id => $model_name ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $model_id ),
							selected( get_option( 'wp_autoplugin_planner_model' ), $model_id, false ),
							esc_html( $model_name )
						);
					}
					echo '</optgroup>';
				}

				if ( ! empty( $custom_models ) ) {
					echo '<optgroup label="' . esc_attr__( 'Custom Models', 'wp-autoplugin' ) . '">';
					foreach ( $custom_models as $custom_model ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $custom_model['name'] ),
							selected( get_option( 'wp_autoplugin_planner_model' ), $custom_model['name'], false ),
							esc_html( $custom_model['name'] )
						);
					}
					echo '</optgroup>';
				}
				?>
			</select>
		</div>

		<div style="margin-bottom: 15px;">
			<label for="modal-coder-model" style="display: block; font-weight: bold; margin-bottom: 5px;"><?php esc_html_e( 'Coder Model:', 'wp-autoplugin' ); ?></label>
			<select id="modal-coder-model" style="width: 100%;">
				<option value="" <?php selected( get_option( 'wp_autoplugin_coder_model' ), '', false ); ?>><?php esc_html_e( 'Use Default Model', 'wp-autoplugin' ); ?></option>
				<?php
				foreach ( self::get_models() as $provider => $models ) {
					echo '<optgroup label="' . esc_attr( $provider ) . '">';
					foreach ( $models as $model_id => $model_name ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $model_id ),
							selected( get_option( 'wp_autoplugin_coder_model' ), $model_id, false ),
							esc_html( $model_name )
						);
					}
					echo '</optgroup>';
				}

				if ( ! empty( $custom_models ) ) {
					echo '<optgroup label="' . esc_attr__( 'Custom Models', 'wp-autoplugin' ) . '">';
					foreach ( $custom_models as $custom_model ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $custom_model['name'] ),
							selected( get_option( 'wp_autoplugin_coder_model' ), $custom_model['name'], false ),
							esc_html( $custom_model['name'] )
						);
					}
					echo '</optgroup>';
				}
				?>
			</select>
		</div>

		<div style="margin-bottom: 20px;">
			<label for="modal-reviewer-model" style="display: block; font-weight: bold; margin-bottom: 5px;"><?php esc_html_e( 'Reviewer Model:', 'wp-autoplugin' ); ?></label>
			<select id="modal-reviewer-model" style="width: 100%;">
				<option value="" <?php selected( get_option( 'wp_autoplugin_reviewer_model' ), '', false ); ?>><?php esc_html_e( 'Use Default Model', 'wp-autoplugin' ); ?></option>
				<?php
				foreach ( self::get_models() as $provider => $models ) {
					echo '<optgroup label="' . esc_attr( $provider ) . '">';
					foreach ( $models as $model_id => $model_name ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $model_id ),
							selected( get_option( 'wp_autoplugin_reviewer_model' ), $model_id, false ),
							esc_html( $model_name )
						);
					}
					echo '</optgroup>';
				}

				if ( ! empty( $custom_models ) ) {
					echo '<optgroup label="' . esc_attr__( 'Custom Models', 'wp-autoplugin' ) . '">';
					foreach ( $custom_models as $custom_model ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $custom_model['name'] ),
							selected( get_option( 'wp_autoplugin_reviewer_model' ), $custom_model['name'], false ),
							esc_html( $custom_model['name'] )
						);
					}
					echo '</optgroup>';
				}
				?>
			</select>
		</div>

		<div style="text-align: right;">
			<button id="save-models" class="button button-primary"><?php esc_html_e( 'Save Models', 'wp-autoplugin' ); ?></button>
			<button id="cancel-models" class="button" style="margin-left: 10px;"><?php esc_html_e( 'Cancel', 'wp-autoplugin' ); ?></button>
		</div>
	</div>
</div>

<!-- Token Usage Breakdown Modal -->
<div id="token-breakdown-modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
	<div style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 600px; max-width: 90%; border-radius: 4px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
			<h3 style="margin: 0;"><?php esc_html_e( 'Token Usage Breakdown', 'wp-autoplugin' ); ?></h3>
			<span id="close-token-modal" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
		</div>
		
		<div id="token-breakdown-content" style="margin-bottom: 20px;">
			<p style="text-align: center; color: #666; font-style: italic;"><?php esc_html_e( 'No token usage data available yet.', 'wp-autoplugin' ); ?></p>
		</div>

		<div style="text-align: right;">
			<button id="close-token-breakdown" class="button"><?php esc_html_e( 'Close', 'wp-autoplugin' ); ?></button>
		</div>
	</div>
</div>
