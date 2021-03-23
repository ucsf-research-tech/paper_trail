<?php
namespace ExternalModules;

class FrameworkV5Test extends FrameworkV4Test
{
	protected function getReflectionClass(){
		/**
		 * In v5 all framework methods are automatically accessible via the module object,
		 * making it a safe and more effective test to run against the module instance instead of the framework instance
		 */
		return $this->getInstance();
	}
}