<?php

namespace Src\RouterBoard;

use Src\RouterBoard\SSHConnector;
use Src\RouterBoard\InputParser;
use Exception;

class RouterBoardMod extends AbstractRouterBoard implements IRouterBoardMod {
	
	/**
	 * @see \Src\RouterBoard\IRouterBoard::addNewIP()
	 */
	public function addNewIP(InputParser $input) {
		$dbconnect = new $this->config['database']['data-adapter']($this->config, $this->logger);
		$user = new SSHConnector($this->config, $this->logger);

		if ( !$inputArray = $input->getAddr() )
			throw new Exception("Input array is empty!");

		foreach ($inputArray as $ipAddr) {
			if ( !$dbconnect->checkExistIP( $ipAddr['addr'] ) ) {
			// create backup user and on success add ip to backup list in db
				if ( $identity = $user->createBackupAccount( $ipAddr['addr'], $ipAddr['port'] ) ) {
					if ( $dbconnect->addIP( $ipAddr['addr'], $ipAddr['port'], $identity ) )
						$this->logger->log( "The router: '" . $identity . "'@'" . $ipAddr['addr'] . ":" . $ipAddr['port']. "' has been successfully added to database." );
				}
			}
			else 
				$this->logger->log( "The IP address " . $ipAddr['addr'] . " already exists in the database!", $this->logger->setError() );
		}
	}
	
	/**
	 * @see \Src\RouterBoard\IRouterBoard::deleteIP()
	 */
	public function deleteIP(InputParser $input) {
		$dbconnect = new $this->config['database']['data-adapter']($this->config, $this->logger);
		
		if ( !$inputArray = $input->getAddr() )
			throw new Exception("Input array is empty!");
		
		foreach ($inputArray as $ipAddr) {
				if ( $dbconnect->deleteIP($ipAddr['addr']) ) {
					$this->logger->log( "The IP '" .$ipAddr['addr'] . "' has been deleted successfully.");
					continue;
				}
				$this->logger->log( "The delete of the IP '" .$ipAddr['addr'] . "' from database fails.", $this->logger->setError() );
		}
		
	}
	
	/**
	 * @see \Src\RouterBoard\IRouterBoard::updateIP()
	 */
	public function updateIP(InputParser $input) {
		if ( !$inputArray = $input->getAddr() )
			throw new Exception("Input array is empty!");
		
		if ( count($inputArray) != 2 ) {
			$this->logger->log( "The delete is not possible. Enter only two IP addresses: -i ip -i ip",$this->logger->setError() );
			return;
		}

		$dbconnect = new $this->config['database']['data-adapter']($this->config, $this->logger);

		$ip0 = $dbconnect->checkExistIP( $inputArray[0]['addr'] );
		$ip1 = $dbconnect->checkExistIP( $inputArray[1]['addr'] );
		if ( $ip0 && $ip1 ) {
			$this->logger->log("Both IP addresses already exist in database!",$this->logger->setError() );
			return;
		}
		if ( !$ip0 && !$ip1 )
		{
			$this->logger->log("Neither of the two IP address exist in the database!",$this->logger->setError() );
			return;
		}
		
		$ssh = new SSHConnector($this->config, $this->logger);
		if ($ip0) {
			if ( $identity = $ssh->createBackupAccount( $inputArray[1]['addr'], $inputArray[1]['port'] ) ) {
				if ( $dbconnect->updateIP( $inputArray[0]['addr'], $inputArray[1]['addr'], $inputArray[1]['port'], $identity ))
					$this->logger->log("The update has been successful." );
				else
					$this->logger->log("The update IP '" . $inputArray[0]['addr'] . "' database error.", $this->logger->setError() );
			}
			return;
		}
		if ( $identity = $ssh->createBackupAccount( $inputArray[0]['addr'], $inputArray[0]['port'] ) ) {
			if ( $dbconnect->updateIP( $inputArray[1]['addr'], $inputArray[0]['addr'], $inputArray[0]['port'], $identity ))
				$this->logger->log("The update has been successful." );
			else
				$this->logger->log("The update IP '" . $inputArray[1]['addr'] . "' database error.", $this->logger->setError() );
		}
	}
	
}