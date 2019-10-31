<?php
error_reporting(E_ALL);
require __DIR__ . '/vendor/autoload.php';
$tsv4rdf = new Pensoft\TSV4RDF\TSV4RDF();
ini_set('max_execution_time', 0);
$namespaces = [
	'openbiodiv'=>'http://openbiodiv.net/',
	'dwc'=>'http://rs.tdwg.org/dwc/terms/',
	'fabio'=>'http://purl.org/spar/fabio/',
	'rdfs'=>'http://www.w3.org/2000/01/rdf-schema#',
	
	'xsd'=>'http://www.w3.org/2001/XMLSchema#',
	'prism'=>'http://prismstandard.org/namespaces/basic/2.0/',
	'skos'=>'http://www.w3.org/2004/02/skos/core#',
];

$predefinedPredicates = [
	'taxonID' => 'dwc:taxonID',
	'datasetID' => '!',
	'parentNameUsageID' => 'dwc:parentNameUsageID',
	'acceptedNameUsageID' => 'dwc:acceptedNameUsageID',
	'originalNameUsageID' => 'dwc:originalNameUsageID',
	'scientificName' => 'dwc:scientificName',
	'scientificNameAuthorship' => 'dwc:scientificNameAuthorship',
	'canonicalName' => 'dwc:canonicalName',
	'genericName' => 'dwc:genericName',
	'specificEpithet' => 'dwc:specificEpithet',
	'infraspecificEpithet' => 'dwc:infraspecificEpithet',
	'taxonRank' => 'dwc:taxonRank',
	'nameAccordingTo' => 'dwc:nameAccordingTo',
	'namePublishedIn' => 'dwc:namePublishedIn',
	'taxonomicStatus' => 'dwc:taxonomicStatus',
	'nomenclaturalStatus' => 'dwc:nomenclaturalStatus',
	'taxonRemarks' => 'dwc:taxonRemarks',
	'kingdom' => 'dwc:kingdom',
	'phylum' => 'dwc:phylum',
	'class' => 'dwc:class',
	'order' => 'dwc:order',
	'family' => 'dwc:family',
	'genus' => 'dwc:genus'
];

$publicationDate = '2019-03-18';
$label = 'GBIF Backbone Taxonomy';
$doi = '10.15468/39omei';

$tsv4rdf
	->file(dirname(__FILE__)."/input/Taxon.tsv")
	->setNamespaces($namespaces)
	->setBasePrefix('openbiodiv', 'http://openbiodiv.net/')
	->setPredefinedPredicates($predefinedPredicates)
	->actionInitialize(function($row, $object) use ($publicationDate,$label,$doi) {
		$baseIRI = $object->baseIRI;
		$object->setOneTimeTriple($baseIRI.$object->baseUUID, 'a', 'fabio:Database', true);
		$object->setOneTimeTriple($baseIRI.$object->baseUUID, 'rdfs:label', $label);
		$object->setOneTimeTriple($baseIRI.$object->baseUUID, 'prism:publicationDate', $publicationDate, false, '^^xsd:date');
		$object->setOneTimeTriple($baseIRI.$object->baseUUID, 'prism:doi', $doi, false);
	})
	->actionBeforeTriples(function(&$row, $object) use ($publicationDate,$label,$doi) {
		$baseIRI = $object->baseIRI;
		$basePrefix = $object->basePrefix;
		$newUUID = $object->uuid();
		$object->setSubjectsSuffix('-'.$object->getObject($row, 'taxonID'));
		$object->setTriple($object->getSubject(), 'a', $basePrefix.'TaxonomicConcept', true);
		
		
		$object->setTriple($object->getSubject(), $object->getPredicate('taxonID'), $object->getObject($row, 'taxonID'));
		$object->setTriple($object->getSubject(), $object->getPredicate('taxonomicStatus'), $object->getObject($row, 'taxonomicStatus'));
		$object->setTriple($object->getSubject(), $object->getPredicate('parentNameUsageID'), $object->getObject($row, 'parentNameUsageID'));
		$object->setTriple($object->getSubject(), 'skos:broader', "<".$baseIRI.$object->baseUUID.'-'.$object->getObject($row, 'parentNameUsageID').">", true );
		$object->setTriple($object->getSubject(), $basePrefix.'taxonomicConceptLabel', "<".$object->getSubject('','-label').">", true);
		$object->setTriple($object->getSubject('','-label'), 'a', $basePrefix.'TaxonomicConceptLabel', true);
		
		$object->setTriple($object->getSubject('','-label'), 'rdfs:label', $object->getObject($row, 'scientificName').' sec. '.$label.' ('.$publicationDate.')');
		$object->setTriple($baseIRI.$newUUID, 'a', $basePrefix.'RCC5Statement', true);
		$object->setTriple($baseIRI.$newUUID, $basePrefix.'rcc5fromRegion', "<".$baseIRI.$object->baseUUID.'-'.$object->getObject($row, 'taxonID').">", true);
		$object->setTriple($baseIRI.$newUUID, $basePrefix.'rcc5toRegion', "<".$baseIRI.$object->baseUUID.'-'.$object->getObject($row, 'parentNameUsageID').">", true);
		$object->setTriple($baseIRI.$newUUID, $basePrefix.'rcc5relationType', $basePrefix.'ProperPart_INT', true);
		
		

		$object->removeRowField('taxonID', $row);
		$object->removeRowField('taxonomicStatus', $row);
		$object->removeRowField('parentNameUsageID', $row);
		// $object->removeRowField('scientificName', $row);
		$object->setSubjectsSuffix('-label');
		
		$object->setTriple($object->getSubject(), $basePrefix.'nameAccordingTo', "<".$baseIRI.$object->baseUUID.">", true);
	})
	->actionAfterTriples(function(&$row, $object){
		$basePrefix = $object->basePrefix;
		$object->removeSubjectsSuffix('-label');
		$object->setTriple($object->getSubject('','-scName'), 'a', $basePrefix.'ScientificName', true);
		$object->setTriple($object->getSubject('','-scName'), 'rdfs:label', $object->getObject($row, 'scientificName'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('scientificName'), $object->getObject($row, 'scientificName'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('scientificNameAuthorship'), $object->getObject($row, 'scientificNameAuthorship'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('canonicalName'), $object->getObject($row, 'canonicalName'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('genericName'), $object->getObject($row, 'genericName'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('specificEpithet'), $object->getObject($row, 'specificEpithet'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('infraspecificEpithet'), $object->getObject($row, 'infraspecificEpithet'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('taxonRank'), $object->getObject($row, 'taxonRank'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('nameAccordingTo'), $object->getObject($row, 'nameAccordingTo'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('namePublishedIn'), $object->getObject($row, 'namePublishedIn'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('taxonomicStatus'), $object->getObject($row, 'taxonomicStatus'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('nomenclaturalStatus'), $object->getObject($row, 'nomenclaturalStatus'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('taxonRemarks'), $object->getObject($row, 'taxonRemarks'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('kingdom'), $object->getObject($row, 'kingdom'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('phylum'), $object->getObject($row, 'phylum'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('class'), $object->getObject($row, 'class'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('order'), $object->getObject($row, 'order'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('family'), $object->getObject($row, 'family'));
		$object->setTriple($object->getSubject('','-scName'), $object->getPredicate('genus'), $object->getObject($row, 'genus'));
		$object->setTriple($object->getSubject(), $basePrefix.'scientificName', "<".$object->getSubject('','-scName').">", true);
	})
	
	// ->setLimit(3)
	->toFile(dirname(__FILE__).'/output/output.ttl');
	// ->toFiles(dirname(__FILE__).'/processed/filename_$1.ttl');
	// ->toAPI($endpoint = 'http://graph.openbiodiv.net:7777//repositories/obkms_i10/statements', $method = 'POST', $options = array('USERPWD'=>'admin:norbertWiener12wurst*'), $headers = array('Content-Type: application/x-trig'));