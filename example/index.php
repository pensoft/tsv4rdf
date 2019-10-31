<?php

use Pensoft\TSV4RDF\TSV4RDF;

$directory = dirname(__DIR__).DIRECTORY_SEPARATOR.'packagist/pensoft/tsv4rdf/example/';
$tsv4rdf = new TSV4RDF();
$tsv4rdf
	->file($directory .'cities.tsv')
	->setPredefinedPredicates(
		array(
			'iso2' => 'fabio:ISO2',
			'province' => '!',
		)
	)
	->setNamespaces(
		array(
			'openbiodiv' => 'http://openbiodiv.net/',
		)
	)
	->actionInitialize(function($row, $object) {
		$baseIRI = $object->baseIRI;
		$object->setNamespace('fabio', 'http://purl.org/spar/fabio/');
	})
	->actionBeforeTriples(function(&$row, $object) {
		$object->setOneTimeTriple($object->getSubject(), $object->getPredicate('pop'), $object->getObject($row, 'pop'), 0, '^^xsd:string');
		$object->setTriple($object->getSubject(), $object->getPredicate('pop'), $object->getObject($row, 'pop'), 0, '^^xsd:integer');
		$object->removeRowField('pop', $row);

	})
	// ->setBasePrefix('openbiodiv') // fix is not required 
	->setLimit(0)
	->toFile(public_path('storage') . DIRECTORY_SEPARATOR .'cities.trig');
	// ->toFiles(public_path('storage') . DIRECTORY_SEPARATOR .'cities_$1.trig', '$1', 1000);
	// ->toAPI($endpoint = 'http://graph.openbiodiv.net:7777//repositories/obkms_i10/statements', $method = 'POST', $options = array('USERPWD'=>'admin:norbertWiener12wurst*'), $headers = array('Content-Type: application/x-trig'));