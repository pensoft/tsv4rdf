<?php

namespace Pensoft\TSV4RDF;
use Ixudra\Curl\CurlService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

class TSV4RDF 
{
	const PREFIX = "@prefix";
	public $namespaces = [];
	public $predefinedPredicates = [];
	public $basePrefix;
	public $baseIRI;
	public $baseUUID;
	public $limit = 0;
	private $resource;
	private $subscribes = array();
	private $fields = [];
	private $prefixes = [];
	private $triples = [];
	private $oneTimetriples = [];
	private $blockTriples = [];
	private $performanceStart;
	private $performanceEnd;
	private $subjects = [];
	private $delimeter = "\t";
	
	public function __construct () {
		// var_dump($this->baseUUID);
		// $this->setBaseUUID();
	}

	public function file ($filename) {
		if(file_exists($filename)){
			$this->resource = $this->handle($filename);
		}else {
			throw new \Exception("This file is not exist!");
		}
		return $this;
	}
	
	public function setNamespaces ($data) {
		foreach ($data as $prefix => $resource) {
			$this->setNamespace ($prefix, $resource);
		}
		return $this;
	}
	
	public function getNamespace($name, $default = null){
		if(isset($this->namespaces[$name])){
			return $this->namespaces[$name];
		}
		return $default;
	}
	
	public function setNamespace ($prefix, $resource) {
		$this->namespaces[rtrim($prefix, ':').':'] = $resource;
		return $this;
	}
	
	public function setBasePrefix ($prefix = null) {
		if(strlen($prefix) == 0 || $prefix == null){
			$lNamespaces = array_keys($this->namespaces);
			$prefix = array_shift($lNamespaces);
		}
		$lPrefix = rtrim($prefix, ':').':';
		$lResource = $this->getNamespace($lPrefix);
		$this->basePrefix = $lPrefix;
		$this->setBaseIRI($lResource);
		return $this;
	}

	public function getBasePrefix () {
		return $this->basePrefix;
	}
	
	public function setPredefinedPredicates ($value) {
		$this->predefinedPredicates = $value;
		return $this;
	}
	
	public function actionInitialize ($callback) {
		if( is_callable($callback) ){
			$this->subscribes['actionInitialize'] = $callback;
		}
		return $this;
	}
	
	public function actionBeforeNamespaces ($callback) {
		if( is_callable($callback) ){
			$this->subscribes['actionBeforeNamespaces'] = $callback;
		}
		return $this;
	}
	
	public function actionAfterNamsespaces ($callback) {
		if( is_callable($callback) ){
			$this->subscribes['actionAfterNamsespaces'] = $callback;
		}
		return $this;
	}
	
	public function actionBeforeTriples ($callback) { // publisher
		if( is_callable($callback) ){
			$this->subscribes['actionBeforeTriples'] = $callback;
		}
		return $this;
	}
	
	public function actionAfterTriples ($callback) { // publisher
		if( is_callable($callback) ){
			$this->subscribes['actionAfterTriples'] = $callback;
		}
		return $this;
	}
	
	private function dispatchAction ($name, &$message, $involkeOneTime=false) {
		if(isset($this->subscribes[$name])){
			$object = $this;
			$out = call_user_func_array($this->subscribes[$name], [&$message, $object]);
			if($involkeOneTime){
				unset($this->subscribes[$name]);
			}
			return $out;
		}
	}
	
	private function dispatchActions () {
		foreach($this->subscribes as $name => $subscribe){
			return dispatchAction($name);
		}
	}
	
	public function toFile ($filename) {
		if(file_exists( dirname($filename) ) and filesize( $filename ) > 0){
			file_put_contents($filename, "");
		}
		$this->mapping(function($turtle, $line) use ($filename){
			
			if(file_exists( dirname($filename) )){
				if(file_put_contents($filename, $turtle, FILE_APPEND ) === false){
					throw new \Exception("Can not save content in file");
				}
			}else{
				throw new \Exception("The location to the file is not exist");
			}
			
		});
	}
	
	public function toFiles ($filename, $replace = '$1', $rowsInFile = 1) {
		
		$i = 0;
		$this->mapping(function($turtle, $line) use ($filename, $replace, &$i, $rowsInFile){
			$store_flag = $rowsInFile > 1 ? FILE_APPEND : 0;
			$lLine = $line;
			if($rowsInFile > 1){
				if($line % $rowsInFile == 0){
					$lLine = $i;
					$i++;
				}else{
					$lLine = $i;
				}
			}
			$filename = str_replace($replace, $lLine, $filename);
			if(file_exists( dirname($filename) )){
				if(file_put_contents($filename, $turtle, $store_flag ) === false){
					throw new \Exception("Can not save content in file");
				}
			}else{
				throw new \Exception("The location to the file is not exist");
			}
			
		});
		
		return $this;
	}
	
	public function toString () {
		$this->mapping(function($turtle, $line) {
			print $turtle;
		});
		return $this;
	}
	
	public function toAPI ($endpoint, $method = 'GET', $options = array(), $headers = array()) {
		$this->mapping(function($turtle, $line) use ($endpoint, $method, $options, $headers){

			$curl = CurlService::to($endpoint)
				->withData($turtle)
				->returnResponseObject();				
			foreach($options as $name => $option) {
				$curl = $curl->withOption($name, $option);
			}
			foreach($headers as $header) {
				$curl = $curl->withHeader($header);
			}
			switch (strtolower($method)) {
				case 'get': $curl = $curl->get(); break;
				case 'post': $curl = $curl->post(); break;
				case 'put': $curl = $curl->put(); break;
				case 'patch': $curl = $curl->patch(); break;
				case 'delete': $curl = $curl->delete(); break;
			}

			if(!in_array($curl->status, array(200,201,202,203,204,205,206,207,208,226))){
				throw new Exception($curl->content);
			}
			return $curl;
		
		});
	}
	
	public function setLimit ($value) {
		$this->limit = $value;
		return $this;
	}
	
	public function totalLines () {
		$total = 0;
		if ($this->resource && $total>=0) {
			while ( fgets($this->resource) !== false ) {
				$total++;
			}
		}
		return $total;
	}

	private function mapping ($cb) {
		if($this->getBasePrefix() === NULL){
			// try to set first namespace if exist
			$this->setBasePrefix();
		}
		if($this->baseUUID === NULL){
			$this->setBaseUUID();
		}
		$this->validator();
		$this->purformance();
		$lines = 0;
		if ($this->resource) {
		    while ( ($row = fgetcsv( $this->resource, 0, $this->getDelimeter() )) !== false && ( $lines < $this->limit + 1 || $this->limit == 0 ) ) {
			    if($this->setFields($row)){
		    		$lines++;
		    		continue;
		    	}
				
				$this->setSubject($this->baseIRI.$this->baseUUID);
				
				$turtle = $this->createTurtle($row);
				$cb($turtle, $lines);
				
				if($lines % 1000 == 0){
					print "processed lines: ".$lines. "\n";
				}
				
				$this->reset();
				$lines++;
		    }
			
			$this->fields = [];
			$this->blockTriples = [];
			fclose($this->resource);
		}
		$this->purformance(false);
		
	}
	
	private function validator () {
		if($this->baseIRI == null){
			throw new \Exception("baseIRI is require");
		}
		if($this->basePrefix == null){
			throw new \Exception("basePrefix is require");
		}
	}
	
	private function purformance ($start = true) {
		if($start){
			$this->performanceStart = microtime(true);
			return;
		}
		if($start == false){
			$this->performanceEnd = microtime(true);
			$execution_time = ($this->performanceEnd - $this->performanceStart);
			echo "\nTotal execution time: ".round($execution_time, 2)." sec\n";
		}
	}
	
	private function handle ($filename) {
		$handle = fopen($filename, "r");
		if($handle){
			return $handle;
		}
		return false;
	}

	private function setFields ($row) {
		if( empty($this->fields) ){
			foreach ($row as $i => $field) {
				$this->fields[$i] = $field;
			}
			return true;
		}
		return false;
	}
	
	public function getField ($name, $default = null) {
		if(isset($this->fields[$name])){
			return $this->fields[$name];
		}
		
		if( in_array($name, $this->fields) ){
			$key = array_search($name, $this->fields);
			return $this->fields[$key];
		}
		
		return $default;
	}
	
	public function getFieldId ($name) {
		$name = $this->getField($name);
		return array_search($name, $this->fields);
	}
	
	public function setSubjectsSuffix ($suffix) {
		$this->setSubject($this->getSubject('', $suffix));
	}
	
	public function removeSubjectsSuffix ($suffix) {
		$this->setSubject(str_replace($suffix, '', $this->getSubject()));
	}

	private function setSubject ($name) {
	 	$this->subject = $name;
	}
	
	public function getSubject ($prefix='', $suffix='') {
		return $prefix.$this->subject.$suffix;
	}
	
	public function removeRowField ($name, &$row) {
		unset($row[$this->getFieldId($name)]);
	}
	
	// getPredicate(1 || 'taxonID')
	public function getPredicate ($name) {
		$field = $this->getField($name, $default = '');
		if(isset($this->predefinedPredicates[$field]) && $this->isColumnBlocked($this->predefinedPredicates[$field])){
			return false;
		}
		if($this->hasPredefinedPredicate($field)){
			return $this->predefinedPredicates[$field];
		}
		return $this->basePrefix ? $this->basePrefix.$field : ":".$field;
	}
	
	public function isColumnBlocked ($name) {
		if(stripos($name, '!') === 0){
			return true;
		}
		
		return false;
	}
	
	// getObject('scientificName' || 4) 
	public function getObject ($row, $name, $default = null) {
		$key = $this->getFieldId($name);
		
		if(isset($row[$key])){
			return $row[$key];
		}
		
		if( array_key_exists($key, $row) ){
			return $row[$key];
		}
		
		return $default;
	}
	
	public function setPrefixes () {
		foreach ($this->namespaces as $prefix => $IRI) {
			$this->prefixes[] = self::PREFIX." $prefix <$IRI> .";
		}
	}
	
	public function setTriples ($row) {
		$row = $this->notEmptyRows($row);
		foreach ($row as $i => $o) {
			$s = $this->getSubject();
			$p = $this->getPredicate($i);
			if($p){
					$this->setTriple($s,$p,$o);
			}
		}
	}
	
	public function setTriple ($s,$p,$o, $is_object = false, $xsdType = "^^xsd:string") {
		if($o != NULL || $o != ""){
			$o = $is_object ? $o : '\''.addslashes($o).'\''.$xsdType;
			$this->triples[] = "<$s> $p $o .";
		}
	}
	
	public function setOneTimeTriple ($s,$p,$o, $is_object = false, $xsdType = "^^xsd:string") {
			$serialized = base64_encode($s.$p.$o);
			if(!in_array($serialized, $this->blockTriples)){
				$o = $is_object ? $o : "\"$o\"".$xsdType;
				$this->oneTimetriples[] = "<$s> $p $o .";
				$this->blockTriples[] = $serialized;
			}
	}
	
	public function createTurtle ($row) {
		$this->dispatchAction('actionBeforeNamespaces', $row);
		$this->setPrefixes();
		$this->dispatchAction('actionAfterNamespaces', $row);
		
		$this->dispatchAction('actionInitialize', $row, true);
        
		$this->dispatchAction('actionBeforeTriples', $row);
		$this->setTriples($row);
		$this->dispatchAction('actionAfterTriples', $row);
		
		return $this->toString_();
	}
	
	private function toString_ () {
		$predixes = implode("\n", $this->prefixes);
		$oneTimeTriples = implode("\n", $this->oneTimetriples);
		$triples = '';
		if(count($this->triples) > 0){
			$triples .= "<".$this->baseIRI.$this->baseUUID.">\n{\n";
		}
		$triples .= implode("\n", $this->triples);
		if(count($this->triples) > 0){
			$triples .= "\n}\n";
		}
		
		return $predixes . "\n" . $oneTimeTriples . "\n" . $triples . "\n";
	}
	
	private function reset () {
		$this->prefixes = [];
		$this->triples = [];
		$this->oneTimetriples = [];
		
	}
	
	public function notEmptyRows ($data) {
		return 
			array_filter($data, function($row){
				return strlen($row) > 0;
			});
		}
	
	public function hasPredefinedPredicate ($field) {
		if(!array_key_exists($field, $this->predefinedPredicates)){
			return false;
		}
		
		return true;	
	}
	
	public function uuid ($suffix = '') {
	  $data = openssl_random_pseudo_bytes(16);
	  $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 
	  $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 
	  return strtoupper( vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)).$suffix );
	}
	
	public function setBaseIRI ($value) {
		$this->baseIRI = $value;
		return $this;
	}
	
	public function setBaseUUID ($value = NULL) {
		if(!$value){
			$value = $this->uuid();
		}
		$this->baseUUID = strtoupper( $value );
		return $this;
	}

	public function setDelimeter ($value) {
		$this->delimeter = $value;
		return $this;
	}

	public function getDelimeter () {
		return $this->delimeter;
	}

}



?>