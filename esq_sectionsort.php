//<?php

if(@txpinterface == 'admin') {
	register_callback('esq_sectionsort', 'section_ui', 'extend_detail_form');
	register_callback('esq_sectionsort_js', 'section');
	register_callback('esq_sectionsort_prefs', 'plugin_prefs.esq_sectionsort');
	register_callback('esq_sectionsort_setup', 'plugin_lifecycle.esq_sectionsort');
	add_privs('plugin_prefs.esq_sectionsort','1,2,3,4,5,6');
}

function esq_sectionsort($event, $step, $data, $rs) {
	if (esq_sectionsort_checkDB() == true) {
		return n.n.tr(fLabelCell('Sort value:').fInputCell('sectionsort', 'Loading...', 1, 20), ' class="sectionsort"');
	} else {
		return '';
	}
}

function esq_sectionsort_js() {
	echo <<<EOF
<script language="javascript" type="text/javascript">
	$(document).ready(function() {
		$.ajax({
			url: 'index.php',
			type: 'get',
			dataType: 'text',
			data: {event: 'plugin_prefs.esq_sectionsort', step: 'sectionsort', method: 'get'},
			success: function(returnData) {
				eval(returnData);
				$('form input[name=sectionsort]').each(function() {
					if ($('input[name=name]', $(this).parents('form')).val() == 'default') {
						$(this).val('').hide().parents('.sectionsort').hide();
					} else {
						$(this).val(sectionsort[$('input[name=name]', $(this).parents('form')).val()]).parents('form').submit(function() {
							var ajaxStatus;
							$.ajax({
								async: false,
								url: 'index.php',
								type: 'get',
								dataType: 'text',
								data: {event: 'plugin_prefs.esq_sectionsort', step: 'sectionsort', method: 'put', name: $('input[name=name]', $(this)).val(), sectionsort: $('input[name=sectionsort]', $(this)).val()},
								success: function(returnData) {
									if (returnData == 'true') {
										ajaxStatus = true;
									} else {
										ajaxStatus = false;
									}
								},
								error: function() {
									ajaxStatus = false;
									alert('Error storing section sort data into database.');
								}
							});
							return ajaxStatus;
						});
					}
				});
			},
			error: function() {
				$('input[name=sectionsort]').val('Error');
				alert('Error loading section sort data from database.');
			}
		});
		$('form input.submitCatch[name=sectionsort]')
	});
</script>
EOF;
}

function esq_sectionsort_prefs($event='', $step='', $message='') {
	if ($step != 'sectionsort') {
		global $txp_user;
		if (safe_field('privs', 'txp_users', 'name=\''.doSlash($txp_user).'\'') != '1') {
			exit(pageTop('Restricted').'<p style="margin-top:3em;text-align:center">'.gTxt('restricted_area').'</p>');
		}
	}
	switch($step) {
		case 'sectionsort':
			esq_sectionsort_ajax();
			break;
		default:
			$step = 'default';
		case 'revertDB':
			$step = 'esq_sectionsort_'.$step;
			$step_result = $step();
			pagetop('esq_sectionsort Options', $step_result);
			break;
	}

	echo '<div style="text-align: center;"><div style="text-align: left; margin: 0 auto; width: 900px;">';
	if (esq_sectionsort_checkDB() == true) {
		echo form(
			'<input type="hidden" name="event" value="plugin_prefs.esq_sectionsort" />'.
			'<input type="hidden" name="step" value="revertDB" />'
			.fInput('submit', 'submit', 'Revert DB & Disable', 'publish').
			'This will undo changes made by <strong>esq_sectionsort</strong> to your Textpattern database, and disable the plugin.'
		);
		echo '<p>This should only be done before uninstalling <strong>esq_sectionsort</strong>, as it removes all \'Sort value\' preferences. This will cause errors in your Pages and Forms where you have used <code>sort="sectionsort"</code>.</p>';
		echo '<p>Reverting the database is not necessary before uninstalling the plugin - it has just been included for those who are meticulous about their DB structure, and don\'t want superfluous columns lying around.</p>';
		echo '<p>If you accidentally revert the database, you can re-enable the plugin to set the database up again for use with <strong>esq_sectionsort</strong>, but your \'Sort value\' preferences will have been lost.</p>';
		echo '<p>Note: Reverting the database effectively runs the MySQL command <code>ALTER TABLE `txp_section` DROP `sectionsort`</code>, which removes the column \'sectionsort\' from the table \'txp_section\' in your Textpattern database. ';
		echo 'This plugin was designed for Textpattern 4.2.0; future versions may actually make use of a column with this name. As such, if you are uninstalling this plugin because it has been made redundant by a feature in a new Textpattern release, please be careful.</p>';
	} else {
		if (($step == 'esq_sectionsort_revertDB') && (!is_array($step_result))) {
			echo '<p>Your Textpattern database has been reverted, and <strong>esq_sectionsort</strong> has (hopefully) been disabled. You may now delete the plugin.</p>';
			echo '<p>If you have done this by accident, enabling <strong>esq_sectionsort</strong> will restore the required column in your Textpattern database. Your \'Sort value\' preferences have been lost however.</p>';
		} else {
			echo '<p>Looks like something went wrong. This is probably because you hit the refresh button in your browser - please avoid doing so.</p>';
			echo '<p>Alternatively your Textpattern database may not have been set up properly during the <strong>esq_sectionsort</strong> installation process. Try checking MySQL user permissions, then disable and re-enable the plugin.</p>';
		}
	}
	echo '</div></div>';
}

function esq_sectionsort_setup($event, $step) {
	switch ($step) {
		case 'installed':
		case 'enabled':
			if (esq_sectionsort_checkDB() != true) {
				return esq_sectionsort_setupDB(ucwords($step));
			} else {
				return '';
			}
			break;
		default:
			return '';
			break;
	}
}

function esq_sectionsort_default() {
	return '';
}

function esq_sectionsort_checkDB() {
	$columns = getRows('SHOW COLUMNS FROM '.safe_pfx('txp_section'));
	foreach($columns as $column => $columnData) {
		$columns[$columnData['Field']] = '';
		unset($columns[$column]);
	}
	return isset($columns['sectionsort']) ? true : false;
}

function esq_sectionsort_setupDB($lifecycle) {
	if (safe_alter('txp_section', 'ADD `sectionsort` VARCHAR(128) NOT NULL') == true) {
		return $lifecycle.' <strong>esq_sectionsort</strong> and DB setup OK.';
	} else {
		return array(
			$lifecycle.' <strong>esq_sectionsort</strong>. DB setup failed.',
			E_ERROR
		);
	}
}

function esq_sectionsort_revertDB() {
	if (safe_alter('txp_section', 'DROP `sectionsort`') == true) {
		if (safe_update('txp_plugin', 'status = 0', 'name = \'esq_sectionsort\'') == true) {
			return 'DB reverted and <strong>esq_sectionsort</strong> disabled OK.';
		} else {
			return array(
				'DB reverted OK. Failed to disabled <strong>esq_sectionsort</strong>.',
				E_ERROR
			);
		}
	} else {
		return array(
			'DB revert failed. No status change.',
			E_ERROR
		);
	}
}

function esq_sectionsort_ajax() {
	header('Content-Type: text/plain');

	switch(gps('method')) {
		case 'get':
			$out = 'var sectionsort = new Array();'."\n";
			foreach(safe_rows('name,sectionsort', 'txp_section', '1=1') as $row) {
				$out .= 'sectionsort[\''.str_replace('\'', '\\\'', $row['name']).'\'] = \''.str_replace('\'', '\\\'', $row['sectionsort']).'\';'."\n";
			}
			echo $out;
			break;
		case 'put':
			echo safe_update('txp_section', 'sectionsort=\''.doSlash(gps('sectionsort')).'\'', 'name=\''.doSlash(gps('name')).'\'') == true ? 'true' : 'false';
			break;
	}

	exit();
}