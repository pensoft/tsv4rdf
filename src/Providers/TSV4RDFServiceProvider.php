<?php

namespace Pensoft\TSV4RDF\Providers;

use Illuminate\Support\ServiceProvider;
use Pensoft\TSV4RDF\TSV4RDF;

class TSV4RDFServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {   
        $this->app->bind('TSV4RDF', function () {
            return new TSV4RDF();
        });
    }
    
    /**
    * @return array
    */
    public function provides() { return array('TSV4RDF'); }
}