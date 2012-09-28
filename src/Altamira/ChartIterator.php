<?php 

namespace Altamira;

class ChartIterator extends \ArrayIterator
{
    
    protected $plugins;
    protected $scripts;
    
    public function __construct( $chartsArray )
    {
        //enforce that this is an array of charts
        $plugins = array();
        $scripts = array();
        
        foreach ($chartsArray as $item) {
            if (! $item instanceOf Chart ) {
                throw new \Exception("ChartIterator only supports an array of Chart instances.");
            }
            
            // time saver -- if it's a chart, we can use this loop to add files, too
            $plugins = array_merge($plugins, $item->getFiles());
            $scripts[] = $item->getScript();
            $this->libraries[$item->getLibrary()] = true;
        }

        // yo dawg...
        // TODO got to make changes here for it to work with AltamiraBundle
        $this->plugins = new FilesRenderer($plugins, 'js/plugins/');
        $this->scripts = new ScriptsRenderer($scripts);
        
        
        parent::__construct($chartsArray);        
    }
    
    
    /**
     * The following render methods are helpers that allow us to group JS easier.
     * We don't handle chart HTML this way since placement and context is a front-end concern.  
     */
    
    
    public function renderPlugins()
    {
        
        while ( $this->plugins->valid() ) {

            $this->plugins->render()
                          ->next();
            
        }
        
        return $this;
        
    }
  
    public function getPlugins() {
        $plugin=array();
        while ($this->plugins->valid() ) {
            $plugin[]=$this->plugins->getScriptPath();
            $this->plugins->next();
        }
        return $plugin;
    }
    
    public function getScripts()
    {
        $script="<script type='text/javascript'>\n";
        while ( $this->scripts->valid() ) {
            
            $script.=$this->scripts->getScript();
            $this->scripts->next();
            
        }
        $script.="\n</script>\n";
        
        return $script;
        
    }

    public function renderScripts()
    {
        echo getScripts();
        return $this;
    }
    
    /* TODO: This code is excessive. Might as well just look at the last value. Methinks this is broken. -jchan */
    public function renderLibraries()
    {
        echo "<script type='text/javascript src='".getLibraries()."'></script>\n";
        return $this;
    }

    /**
     * Instead of printing, return this value
     */
    public function getLibraries() {
        foreach ($this->libraries as $library=>$junk) {
            switch($library) {
                case 'flot':
                    $libraryPath = 'js/jquery.flot.js';
                    break;
                case 'jqPlot':
                default:
                    $libraryPath = 'js/jquery.jqplot.js';
            }
        }
        return $libraryPath;
    }
            
               
    public function renderCss() {
        echo getCss();  
        return $this;
    }
    
    public function getCss()
    {
        foreach ($this->libraries as $library=>$junk) {
            switch($library) {
                case 'flot':
                    break;
                case 'jqPlot':
                default:
                    $cssPath = 'css/jqplot.css';
            }
        
        }
        
        if (isset($cssPath)) {
            return "<link rel='stylesheet' type='text/css' href='{$cssPath}'></link>";
        }
        
        
    }


    //TODO we have to make all the Paths user assignable
    public function getCSSPath() {
        foreach ($this->libraries as $library=>$junk) {
            switch($library) {
                case 'flot':
                    break;
                case 'jqPlot':
                default:
                    $cssPath = 'css/jqplot.css';
            }
        
        }
        
        if (isset($cssPath)) {
            return ($cssPath);
        }
    }
        


    public function getJSLibraries() {
        $libraries= array( "js/jquery.js", $this->getLibraries() );
        $libraries=array_merge(  $libraries ,$this->getPlugins());
        return $libraries;
    }
 
}
