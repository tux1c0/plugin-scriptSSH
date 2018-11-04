<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class scriptssh extends eqLogic {
    /*     * *************************Attributs****************************** */
	
    /*     * ***********************Methode static*************************** */
	public static function dependancy_info() {
		$return = array();
		$return['progress_file'] = jeedom::getTmpFolder('scriptssh') . '/dependance';
		if (exec(system::getCmdSudo() . system::get('cmd_check') . '-E "php\-ssh2" | wc -l') >= 1) {
			$return['state'] = 'ok';
		} else {
			$return['state'] = 'nok';
		}
		return $return;
	}
	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install.sh ' . jeedom::getTmpFolder('scriptssh') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

	public static function update($_eqLogic_id = null) {
		if ($_eqLogic_id == null) {
			$eqLogics = eqLogic::byType('scriptssh');
		} else {
			$eqLogics = array(eqLogic::byId($_eqLogic_id));
		}
		foreach ($eqLogics as $scriptssh) {
			try {
				$scriptssh->getscriptsshInfo();
			} catch (Exception $e) {
				log::add('scriptssh', 'error', $e->getMessage());
			}
		}
	}
	
	public function preUpdate() {
		if ($this->getConfiguration('ip') == '') {
			throw new Exception(__('Le champs IP ne peut pas être vide', __FILE__));
		}
		if ($this->getConfiguration('username') == '') {
			throw new Exception(__("Le champs SSH Nom d'utilisateur ne peut pas être vide", __FILE__));
		}
		if ($this->getConfiguration('password') == '') {
			throw new Exception(__('Le champs SSH Mot de passe ne peut pas être vide', __FILE__));
		}
		if ($this->getConfiguration('portssh') == '') {
			throw new Exception(__('Le champs Port SSH ne peut pas être vide', __FILE__));
		}
	}

	
	public function getscriptsshInfo() {
		// getting configuration
		$IPaddress = $this->getConfiguration('ip');
		$login = $this->getConfiguration('username');
		$pwd = $this->getConfiguration('password');
		$port = $this->getConfiguration('portssh');
		$script = $this->getConfiguration('script');
		$user1 = $this->getConfiguration('username1');
		$pwd1 = $this->getConfiguration('password1');
		$user2 = $this->getConfiguration('username2');
		$pwd2 = $this->getConfiguration('password2');

		// var
		$this->infos = array(
			'status'	=> ''
		);
		
		if ($this->startSSH($IPaddress, $login, $pwd, $port)) {
			$this->infos['status'] = "OK";
		} else {
			$this->infos['status'] = "NOK";
		}
		
		$TabScript = explode("\n",$script);
		
		foreach($TabScript as $cmd) {
			if(strpos($cmd, "#INPUT#") === 0) {
				
			} else {
				$this->execSSH($cmd);
			}
		}
		

			
		$this->updateInfo();
			
		// close SSH
		$this->disconnect($IPaddress);

	}
	
	// replace keywords by parameters
	private function replaceKeyWord($txt, $user1, $pwd1, $user2, $pwd2) {
		$texte = str_replace("#USER1#", $user1, $txt);
		$texte = str_replace("#USER2#", $user2, $texte);
		$texte = str_replace("#PWD1#", $pwd1, $texte);
		$texte = str_replace("#PWD2#", $pwd2, $texte);
		
		return $texte;
	}
	
	// update HTML
	public function updateInfo() {
		foreach ($this->getCmd('info') as $cmd) {
			try {
				$key = $cmd->getLogicalId();
				$value = $this->infos[$key];
				$this->checkAndUpdateCmd($cmd, $value);
				log::add('scriptssh', 'debug', 'key '.$key. ' valeur '.$value);
			} catch (Exception $e) {
				log::add('scriptssh', 'error', 'Impossible de mettre à jour le champs '.$key);
			}
		}
	}
	
	// execute SSH command
	private function execSSH($cmd) {
		try {
			//$cmdOutput = ssh2_exec($this->SSH, $cmd);
			log::add('scriptssh', 'debug', 'Commande '.$cmd);
			//stream_set_blocking($cmdOutput, true);
			//$output = stream_get_contents($cmdOutput);
			
			fwrite($this->shell, $cmd."; echo '#COMMAND_FINISHED#'\n");
            sleep(1);

            // collect and returning data
            $time_start = time();
			$data = "";
			while (true){
				$data .= fgets($this->shell);
				if (strpos($data,"#COMMAND_FINISHED#") !== false) {
					log::add('scriptssh', 'debug', 'Commande OK');
					break;
				}
				if ((time()-$time_start) > 10 ) {
					log::add('scriptssh', 'error', 'Timeout 10s de la commande');
					break;
				}
			}
			
			log::add('scriptssh', 'debug', 'Retour Commande '.$data);
		} catch (Exception $e) {
			log::add('scriptssh', 'error', 'execSSH retourne '.$e);
		}
		return $output;
	}
	
	// establish SSH
	private function startSSH($ip, $user, $pass, $SSHport) {
		try {
			// SSH connection
			if (!$this->SSH = ssh2_connect($ip, $SSHport)) {
				log::add('scriptssh', 'error', 'Impossible de se connecter en SSH à '.$ip);
				return 0;
			}else{
				// SSH authentication
				if (!ssh2_auth_password($this->SSH, $user, $pass)){
					log::add('scriptssh', 'error', 'Mauvais login/password pour '.$ip);
					return 0;
				}else{
					log::add('scriptssh', 'debug', 'Connexion OK pour '.$ip);
				}
			}
			
			// create a shell
			if (!($this->shell = ssh2_shell($this->SSH, 'vt102', null, 80, 40, SSH2_TERM_UNIT_CHARS))) {
				log::add('scriptssh', 'error', 'Impossible de créer un shell avec '.$ip);
				return 0;
			} else {
				stream_set_blocking($this->shell, true);
				// Prompt
				fwrite($this->shell, "\n");
				sleep(1);
				$data = "";
				while ($buf = fgets($this->shell)) {
					$data .= $buf;
				}
				log::add('scriptssh', 'debug', 'Shell OK pour '.$ip);
				log::add('scriptssh', 'debug', 'Prompt '.$data);
				return 1;
			}
		} catch (Exception $e) {
			log::add('scriptssh', 'error', 'startSSH retourne '.$e);
		}			
	}
	
	// Close SSH connection
	private function disconnect($name) {
		try {
			fclose($this->shell);
			
			if (!ssh2_disconnect($this->SSH)) {
				log::add('scriptssh', 'error', 'Erreur de déconnexion pour '.$name);
			}
			$this->SSH = null;
		} catch (Exception $e) {
			log::add('scriptssh', 'error', 'disconnect retourne '.$e);
		}
    }
	
	
	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
		  return $replace;
		}
		$version = jeedom::versionAlias($_version);
		if ($this->getDisplay('hideOn' . $version) == 1) {
		  return '';
		}
		
		foreach ($this->getCmd('info') as $cmd) {
			$replace['#' . $cmd->getLogicalId() . '_history#'] = '';
			$replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
			$replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
			$replace['#' . $cmd->getLogicalId() . '_collect#'] = $cmd->getCollectDate();
			if ($cmd->getIsHistorized() == 1) {
				$replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
			}
			if ($cmd->getIsVisible() == 0) {
				$replace['#' . $cmd->getLogicalId() . '_display#'] = 'none';
			}
		}
		
		foreach ($this->getCmd('action') as $cmd) {
			$replace['#cmd_' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
		}
		
		return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'scriptssh', 'scriptssh')));
	  }
	
		/*     * *********************Methode d'instance************************* */

	public function postSave() {
		
		$scriptsshCmd = $this->getCmd(null, 'status');
		if (!is_object($scriptsshCmd)) {
			log::add('scriptssh', 'debug', 'status');
			$scriptsshCmd = new scriptsshCmd();
			$scriptsshCmd->setName(__('Statut', __FILE__));
			$scriptsshCmd->setEqLogic_id($this->getId());
			$scriptsshCmd->setLogicalId('status');
			$scriptsshCmd->setType('info');
			$scriptsshCmd->setSubType('string');
			$scriptsshCmd->save();
		}
		
		$scriptsshCmd = $this->getCmd(null, 'refresh');
		if (!is_object($scriptsshCmd)) {
			log::add('scriptssh', 'debug', 'refresh');
			$scriptsshCmd = new scriptsshCmd();
			$scriptsshCmd->setName(__('Rafraîchir', __FILE__));
			$scriptsshCmd->setEqLogic_id($this->getId());
			$scriptsshCmd->setLogicalId('refresh');
			$scriptsshCmd->setType('action');
			$scriptsshCmd->setSubType('other');
			$scriptsshCmd->save();
		}
	}
	
	public function postUpdate() {
		$cmd = $this->getCmd(null, 'refresh');
		if (is_object($cmd)) { 
			 $cmd->execCmd();
		}
    }
	
}

class scriptsshCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */


    public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		switch ($this->getLogicalId()) {
			case "refresh":
				$eqLogic->getscriptsshInfo();
				log::add('scriptssh','debug','refresh ' . $this->getHumanName());
				break;
 		}
		return true;
	}

    /*     * **********************Getteur Setteur*************************** */
}