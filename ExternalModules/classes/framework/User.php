<?php
namespace ExternalModules;

class User
{
	function __construct($framework, $username){
		$this->framework = $framework;
		$this->username = $username;
	}

	function getRights($project_ids = null){
		return ExternalModules::getUserRights($project_ids, $this->username);
	}

	function hasDesignRights($project_id = null){
		if($this->isSuperUser()){
			return true;
		}

		if(!$project_id){
			$project_id = $this->framework->requireProjectId();
		}

		$rights = $this->getRights($project_id);
		return $rights['design'] === '1';
	}

	private function getUserInfo(){
		if(!$this->user_info){
			$results = $this->framework->query("
				select *
				from redcap_user_information
				where username = ?
			", [$this->username]);

			$this->user_info = $results->fetch_assoc();
		}

		return $this->user_info;
	}

	function getUsername() {
		return $this->username;
	}

	function isSuperUser(){
		$userInfo = $this->getUserInfo();
		return $userInfo['super_user'] === 1;
	}

	function getEmail(){
		$userInfo = $this->getUserInfo();
		return $userInfo['user_email'];
	}
}
