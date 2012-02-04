<?php
/*
  Plugin Name:	Simple Flowplayer
  Plugin URI:   http://screennetz.de/develop/simple-flowplayer/
  Description:	Dieses Plugin ermöglicht das einfache präsentieren von Medien aus der Mediathek, sowie externen Quellen, mit hilfe des Flowplayers.
  Author:       tumichnix
  Version:		0.9.5
  Author URI:	http://screennetz.de/
 */

/*
  Copyright 2010-2011 tumichnix (email: tumichnix at screennetz.de)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class ScreenSimpleFlowplayer {

    /**
     * Flowplayer version
     * @var string
     */
    private $flowplayerVersion = '3.2.4';

    /**
     * URL to plugin
     * @var string
     */
    private $pluginUrl;

    /**
     * Player ID
     * @var string
     */
    private $playerId;

    /**
     * Default-Options
     * @var array
     */
    private $defaultOptions = array(
        'autoplay' => 1,
        'autobuffer' => 0,
        'width' => 450,
        'height' => 300,
        'fullscreen' => 1,
        'autohide' => 1,
		'transparent' => 0
    );

    /**
     * saved options
     * @var array
     */
    private $options = array();

    /**
     * Database option name
     */
    private $pluginKey = 'plugin_screen_simple_flowplayer';

    /**
     * Plugin-Shortcode
     */
    private $shortcode = 'sf';

    /**
     * init the plugin
     * @return void
     */
    public function init() {
        $this->pluginUrl = WP_PLUGIN_URL . '/simple-flowplayer';
        if (!is_admin()) {
            wp_enqueue_script($this->pluginKey, $this->pluginUrl . '/flowplayer/flowplayer-' . $this->flowplayerVersion . '.min.js');
            add_shortcode($this->shortcode, array($this, 'player'));
        } else {
            add_action('admin_menu', array($this, 'menu'));
        }
    }

    /**
     * activate this plugin
     * @return void
     */
    public function activation() {
        add_option($this->pluginKey, array('Default' => $this->defaultOptions), null, 'no');
    }

    /**
     * deactivate this plugin
     * @return viod
     */
    public function deactivation() {

    }

    /**
     * uninstall this plugin
     */
    public function uninstall() {
        delete_option($this->pluginKey);
    }

    /**
     * the player method (callback)
     * @param array $params
     * @return string
     */
    public function player($params) {
        $notSavedParams = array('id' => null, 'url' => null);
        $this->setPlayerId();
        $this->playerParams[$this->playerId] = array_merge($notSavedParams, $params);
        if (!empty($this->playerParams[$this->playerId]['id'])) {
            $mediaFile = get_post_meta($this->playerParams[$this->playerId]['id'], '_wp_attached_file', true);
            if (empty($mediaFile))
                return $this->getError('No media was found with ID ' . $this->playerParams[$this->playerId]['id'] . '!');
            $this->playerParams[$this->playerId]['url'] = $this->getUploadUrl() . '/' . $mediaFile;
        }
        if (empty($this->playerParams[$this->playerId]['url']))
            return $this->getError('No media!');

        $ext = $this->getFileExtension();
        if (!$ext)
            return $this->getError('No file extension found!');

        $globalOptions = get_option($this->pluginKey);
        $optionKey = (array_key_exists($ext, $globalOptions)) ? $ext : 'Default';
        $globalOptions[$optionKey] = array_merge($notSavedParams, $globalOptions[$optionKey]);

        $this->playerParams[$this->playerId] = shortcode_atts($globalOptions[$optionKey], $this->playerParams[$this->playerId]);
        $this->playerParams[$this->playerId]['ext'] = $ext;

        $player = '<div style="margin-top: 3px; display:block; width:' . $this->playerParams[$this->playerId]['width'] . 'px; height:' . $this->playerParams[$this->playerId]['height'] . 'px;" id="' . $this->playerId . '"></div>';
        $player .= '<script type="text/javascript" language="JavaScript">
                        flowplayer("' . $this->playerId . '", {src: "' . $this->pluginUrl . '/flowplayer/flowplayer-' . $this->flowplayerVersion . '.swf"';
		if (isset($this->playerParams[$this->playerId]['transparent']) && $this->playerParams[$this->playerId]['transparent']) {
			$player .= ", wmode: 'opaque'";
		}
		$player .= '}, {' . $this->getOptions() . '});
                    </script>';
        return $player;
    }

    /**
     * set the backend navigation link
     * @return void
     */
    public function menu() {
        add_options_page('Simple Flowplayer Options', 'Simple Flowplayer', 'manage_options', $this->pluginKey, array($this, 'options'));
    }

    /**
     * Admin Options-Page
     * @return void
     */
    public function options() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        echo('<div class="wrap">');
        if ($this->saveOptions()) {
            echo('<div id="message" class="updated fade"><p>Die Einstellungen wurden erfolgreich gespeichert!</p></div>');
        }

        $this->options = get_option($this->pluginKey);

        echo('<div id="icon-options-general" class="icon32"><br /></div>');
        echo('<h2>Simple Flowplayer Einstellungen</h2>');
        echo('<div style="width: 40%; float: left">');
        echo('<form method="post" action="options-general.php?page=' . $this->pluginKey . '">');

        foreach ($this->options as $ext => $value) {
            echo $this->defaultOptions($ext);
        }

        echo('<input type="hidden" name="action" value="update" />');
        echo('<p class="submit"><input name="saveOptions" type="submit" class="button-primary" value="Speichern" /></p>');
        echo('</form>');
        echo('</div><div style="width: 59%; float: right; padding-left: 10px">');
        echo('<form method="post" action="options-general.php?page=' . $this->pluginKey . '">
			<table class="widefat" style="margin-top: 1em; width: 250px">
				<thead><tr><th scope="col">Datei-Typ anlegen</th></tr></thead>
 				<tbody><tr><td style="text-align: center"><input type="text" name="newExtension" style="width: 90%" /><br /><input name="newExtensionSubmit" type="submit" class="button-primary" value="Anlegen" /></td></tr></tbody>
 			</table>
 			</form>
 			<form method="post" action="options-general.php?page=' . $this->pluginKey . '">
			<table class="widefat" style="margin-top: 1em; width: 250px">
				<thead><tr><th scope="col">Datei-Typ entfernen</th></tr></thead>
 				<tbody><tr><td style="text-align: center"><select name="deleteExtension" style="width: 90%" /><option value=""></option>');
        foreach ($this->options as $ext => $val) {
            if ($ext != 'Default') {
                echo('<option value="' . $ext . '">' . $ext . '</option>');
            }
        }
        echo('	</select><br /><input name="deleteExtensionSubmit" type="submit" class="button-primary" value="L&ouml;schen" /></td></tr></tbody>
 			</table>
 			</form>
 			<table class="widefat" style="margin-top: 1em; width: 250px">
				<thead><tr><th scope="col">Simple Flowplayer</th></tr></thead>
 				<tbody><tr><td><ul><li><a href="http://screennetz.de/develop/simple-flowplayer/">Plugin Website</a></li><li><a href="http://screennetz.de/">Author Website</a></li></ul></td></tr></tbody>
 			</table>');
        echo('</div></div>');
    }

    /**
     * HTML form element for options
     * @param string $fileExtension
     * @return string
     */
    private function defaultOptions($fileExtension = 'Default') {
        $str = '<table class="widefat" style="margin-top: 1em;">
                    <thead>
                        <tr>
                            <th scope="col"><a href="#" onclick="jQuery(\'#' . $fileExtension . '-toggle\').toggle();">' . $fileExtension . '</a></th>
						</tr>
					</thead>
					<tbody>
						<tr id="' . $fileExtension . '-toggle" style="display: none">
							<td>
								<ul>';
        $str .= '<li>
					<input type="hidden" value="0" name="ssf[' . $fileExtension . '][autoplay]" />
					<input type="checkbox" name="ssf[' . $fileExtension . '][autoplay]" value="1" id="' . $fileExtension . '-ap" ' . checked('1', $this->options[$fileExtension]['autoplay'], false) . ' />
					<label for="' . $fileExtension . '-ap">Start playback immediately upon loading.</label>
				</li>';
        $str .= '<li>
					<input type="hidden" value="0" name="ssf[' . $fileExtension . '][autobuffer]" />
					<input type="checkbox" name="ssf[' . $fileExtension . '][autobuffer]" value="1" id="' . $fileExtension . '-ab" ' . checked('1', $this->options[$fileExtension]['autobuffer'], false) . ' />
					<label for="' . $fileExtension . '-ab">Loading of clip into players memory should begin straight away.</label>
				</li>';
        $str .= '<li>
					<input type="hidden" value="0" name="ssf[' . $fileExtension . '][width]" />
					<input type="text" name="ssf[' . $fileExtension . '][width]" value="' . $this->options[$fileExtension]['width'] . '" id="' . $fileExtension . '-w" style="width: 50px" />
					<label for="' . $fileExtension . '-w">Player screen width in pixel.</label>
				</li>';
        $str .= '<li>
					<input type="hidden" value="0" name="ssf[' . $fileExtension . '][height]" />
					<input type="text" name="ssf[' . $fileExtension . '][height]" value="' . $this->options[$fileExtension]['height'] . '" id="' . $fileExtension . '-h" style="width: 50px" />
					<label for="' . $fileExtension . '-h">Player screen height in pixel.</label>
				</li>';
        $str .= '<li>
					<input type="hidden" value="0" name="ssf[' . $fileExtension . '][fullscreen]" />
					<input type="checkbox" name="ssf[' . $fileExtension . '][fullscreen]" value="1" id="' . $fileExtension . '-fs" ' . checked('1', $this->options[$fileExtension]['fullscreen'], false) . ' />
					<label for="' . $fileExtension . '-fs">Should the fullscreen button be visible?</label>
				</li>';
        $str .= '<li>
					<input type="hidden" value="0" name="ssf[' . $fileExtension . '][autohide]" />
					<input type="checkbox" name="ssf[' . $fileExtension . '][autohide]" value="1" id="' . $fileExtension . '-ah" ' . checked('1', $this->options[$fileExtension]['autohide'], false) . ' />
					<label for="' . $fileExtension . '-ah">Autohide the controllbar?</label>
				</li>';
		$str .= '<li>
					<input type="hidden" value="0" name="ssf[' . $fileExtension . '][transparent]" />
					<input type="checkbox" name="ssf[' . $fileExtension . '][transparent]" value="1" id="' . $fileExtension . '-wt" ' . checked('1', $this->options[$fileExtension]['transparent'], false) . ' />
					<label for="' . $fileExtension . '-wt">Use window-mode transparent</label>
				</li>';
        $str .= '				</ul>
							</td>
						</tr>
					</tbody>
				</table>';
        return $str;
    }

    /**
     * save options from backend
     * @return boolean
     */
    private function saveOptions() {
        if (isset($_POST['ssf'])) {
            update_option($this->pluginKey, $_POST['ssf']);
            return true;
        } else if (isset($_POST['newExtensionSubmit']) && !empty($_POST['newExtension'])) {
            $options = get_option($this->pluginKey);
            $ext = strtolower(esc_html($_POST['newExtension']));
            if (!array_key_exists($ext, $options)) {
                $newOptions['Default'] = $options['Default'];
                unset($options['Default']);
                $options[$ext] = $this->defaultOptions;
                ksort($options);
                update_option($this->pluginKey, array_merge($newOptions, $options));
                return true;
            }
        } else if (isset($_POST['deleteExtensionSubmit']) && !empty($_POST['deleteExtension'])) {
            $options = get_option($this->pluginKey);
            $ext = strtolower(esc_html($_POST['deleteExtension']));
            if (array_key_exists($ext, $options) && $ext != 'default') {
                unset($options[$ext]);
                update_option($this->pluginKey, $options);
            }
            return true;
        }
        return false;
    }

    /**
     * get the file extension
     * @return boolean|string
     */
    private function getFileExtension() {
        return ereg(".([^\.]+)$", $this->playerParams[$this->playerId]['url'], $r) ? $r[1] : false;
    }

    /**
     * set player-id
     * @return void
     */
    private function setPlayerId() {
        $this->playerId = 'ssf-' . hash('md5', uniqid());
    }

    /**
     * set error message
     * @param string $error
     * @return string
     */
    private function getError($error) {
        return 'ERROR: ' . $error;
    }

    /**
     * get the upload url
     * @return string
     */
    private function getUploadUrl() {
        $data = wp_upload_dir();
        return $data['baseurl'];
    }

    /**
     * get the javascript option for the player
     * @return string
     */
    private function getOptions() {
        $options = "clip: {
						url: '" . $this->playerParams[$this->playerId]['url'] . "',
						autoPlay: " . (($this->playerParams[$this->playerId]['autoplay'] == 0) ? 'false' : 'true') . ",
						autoBuffering: " . (($this->playerParams[$this->playerId]['autobuffer'] == 0) ? 'false' : 'true') . "
					},
					plugins: { 
						controls: { 
							fullscreen: " . (($this->playerParams[$this->playerId]['fullscreen'] == 0) ? 'false' : 'true') . ",";
        if ($this->playerParams[$this->playerId]['autohide'] == 0) {
            $options .= "autoHide: false,";
		}
        $options .= "	}
					}";
        return $options;
    }
}

$screenSimpleFlowplayer = new ScreenSimpleFlowplayer();
register_activation_hook(__FILE__, array($screenSimpleFlowplayer, 'activation'));
register_deactivation_hook(__FILE__, array($screenSimpleFlowplayer, 'deactivation'));
register_uninstall_hook(__FILE__, array($screenSimpleFlowplayer, 'uninstall'));
add_action('init', array($screenSimpleFlowplayer, 'init'));
?>
