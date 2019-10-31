# TSV4RDF: A TSV (tab-separated values) to TriG (serialization format for RDF) PHP parser

The package processes tabular data in the TSV format and converts it to RDF in the TriG serialization according to the user specifications.

## Requirements

- PHP => 5.3.2
- Composer => 1.6.*
- ext-curl => *

## Installing

Pull this package in through Composer.  

```json
{
    "require": {
        "pensoft/tsv4rdf": "*"
    }
}
``` 

or run in terminal: `composer require pensoft/tsv4rdf`

**Laravel 5.x integration**   

Add the service provider to your `config/app.php` file:

```php
'providers'     => array(

    //...
    Pensoft\TSV4RDF\Providers\TSV4RDFServiceProvider::class,

),
```

Add the facade to your `config/app.php` file:

```php
'aliases' => array(

    //...
    'TSV4RDF' => Pensoft\TSV4RDF\Facades\TSV4RDF::class,

),
```


# Usage



## Example tsv file presented like a table.

**cities.tsv**  
TSV is a file extension for a tab-delimited file

| city | city_ascii | lat | lng | pop | country | iso2 | iso3 | province |
| - | - | - | - | - | - | - | - | - |
| Qal eh-ye Now | Qal eh-ye | 34.98300013 | 63.13329964 | 2997 | Afghanistan | AF | AFG | Badghis |
Chaghcharan|Chaghcharan|34.5167011|65.25000063|15000|Afghanistan|AF|AFG|Ghor
Lashkar Gah|Lashkar Gah|31.58299802|64.35999955|201546|Afghanistan|AF|AFG|Hilmand

## Initialize `TSV4RDF` package

**Via Laravel 5.4**
```php
use Pensoft\TSV4RDF\TSV4RDF;

$tsv4rdf = new TSV4RDF();
```

## Load (.tsv) file

```php
$tsv4rdf->file('cities.tsv');
```

## Load (.csv) file

```php
$tsv4rdf->file('cities.csv');
$tsv4rdf->setDelimeter(',');
```
*This package can stream and `csv` files but must to set comma separator*

## Include namespaces

```php
$tsv4rdf->setNamespace('geo', 'http://rdf.insee.fr/def/geo#');
```
or
```php
$tsv4rdf->setNamespaces( array('geo' => 'http://rdf.insee.fr/def/geo#', 'geo2' => 'http://www.opengis.net/geosparql#') );
```

## Set base prefix to triples

```php
$tsv4rdf->setBasePrefix ('geo');
```
*by default is `NULL`*
> If is not set base prefix by default before to start stream data takes from namespace the first and applies it.

## How to predefine predicates

```php
$tsv4rdf->setPredefinedPredicates( array('city' => 'geo2:city') );
```

**Exclude from input file columns**  

```php
$tsv4rdf->setPredefinedPredicates( array('city_ascii' => '!') );
```

## Dispatch actions
*Actions helps to change or involke `triples` and `namespaces` during in stream the turtles.*

`actionInitialize(function($row, $class){ })`
>This action is dispatch only once time before to load triples and namespace. The param `$class` is `tsv4rdf` and can involke all public functions

**All actions**  

`actionBeforeTriples(function($row, $class){ })`  
*The action `actionBeforeTriples` is involked before build all triples from csv.*

`actionAfterTriples(function($row, $class){ })`   
*The action `actionBeforeTriples` is involked after build all triples from csv.*

`actionBeforeNamespaces(function($row, $class){ })`  
*Adds prefixes before build namespaces in turtle.*

`actionAfterNamespaces(function($row, $class){ })`
*Adds prefixes after build all exists namespaces in turtle..*


**Available methods in `actions`**

`setOneTimeTriple()`

Renders `triple` only one time

```php
actionInitialize(function($row, $object){
  $object->setOneTimeTriple($subject = '<http://openbiodiv.net/d3be573a-f04e-411a-adbc-45b048ded905-8708510>', 
  $predicate = 'a', 
  $object = 'fabio:Label', 
  $is_object = true
  );
});
```

`setTriple()`

Stores `triple` in stock of triples

```php
actionInitialize(function($row, $object){
  $object->setTriple(
    $subject = '<http://openbiodiv.net/d3be573a-f04e-411a-adbc-45b048ded905-8708510>',
    $predicate = 'a', 
    $object = 'openbiodiv:Label', 
    $is_object = true
  );
});
```

`setSubjectsSuffix()`

The `setSubjectsSuffix` function adds to the main subject suffix.

```php
actionInitialize(function($row, $object){
  $object->setSubjectsSuffix('-label');
  
  echo $object->getSubject();
});
```

`removeRowField()`

The `removeRowField` skips a value from `tsv` resource.

```php
actionInitialize(function($row, $object){
  $object->removeRowField('country', $row);
});
```

`removeSubjectsSuffix()`

The `removeSubjectsSuffix` removes a suffix from subject.

```php
actionInitialize(function($row, $object){
  $object->removeSubjectsSuffix('-label');
  
  echo $object->getSubject();
});
```

`getSubject()`

The `getSubject` returns a value with some suffix and prefix. By default `$suffix` and `$prefix` are empty.

```php
actionInitialize(function($row, $object){
  $object->getSubject(
    $suffix = '',
    $prefix = '';
  );
});
```

`getPredicate()`

The `getPredicate` returns a value from some resource column name. The parameter can be a number or name of column.

```php
actionInitialize(function($row, $object){
  $object->getPredicate('country');
});
```

`getObject()`

The `getObject` returns a value from some column. The parameter can be a number or name of column.

```php
actionInitialize(function($row, $object){
  $object->getObject($row, 'country', $default = null);
});
```

`setNamespace()`

The `setNamespace` records `prefix` and `resource` in an array of namespaces.

```php
actionInitialize(function($row, $object){
  $object->setNamespace(
    $prefix = '',
    $resource = ''
  );
});
```



## Limit input streaming

```php
$tsv4rdf->setLimit( 1000 );
```
*by default limit is set to `0` and read all input data*


## Output data

```php
$tsv4rdf->toFile( 'cities.trig' );
```
*The output data is appended to singular file and could be set with various extensions*

```php
$tsv4rdf->toFiles( 'cities_$1.trig', $1, 1000 );
```
*The output data is splitted by files with 1000 rows from input data*

```php
$tsv4rdf->toAPI ($endpoint, $method = 'GET', $options = array(), $headers = array())
```
`$endpoint` - URL  
`$method` - 'GET','POST', 'PUT', 'PATCH' and etc.
`$options` - see [curl_setopt](http://php.net/manual/en/function.curl-setopt.php)  
`$headers` - see CURLOPT_HEADEROPT [link](http://php.net/manual/en/function.curl-setopt.php)  

```php
$tsv4rdf->toString ()
```
*Desplay output data*


[Work example](./example)

