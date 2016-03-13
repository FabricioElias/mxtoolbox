<?php

use MxToolbox\MxToolbox;
use MxToolbox\Exception\MxToolboxException;

require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'../src/MxToolbox/autoload.php';

try {
	/**
	 * real IP address of mail server for test
	 */
	$addr = '194.8.253.5';
	/**
	 * Create MxToolbox object
	 */
	$mxt = new MxToolbox();
	/**
	 * Do any only if IP address have a reverse PTR record
	 * getPTR() and getDomainName() return FALSE without calling checkExistPTR()
	 */
	if ( $mxt->checkExistPTR($addr) ) {
		// print the reverse PTR record
		echo $mxt->getPTR().PHP_EOL;
		// print the domain name from reverse PTR record
		echo $mxt->getDomainName().PHP_EOL;
		// print list of a MX records of the domain name
		var_dump($mxt->getMXRecords($mxt->getDomainName()));
	}

} catch ( MxToolboxException $e ) {
	echo 'Caught exception: ',  $e->getMessage(), "\n";
}