<?php 

namespace Altamira\JsWriter;

use Altamira\JsWriter\Ability;

class Flot 
    extends JsWriterAbstract
    implements Ability\Cursorable,
               Ability\Datable,
               Ability\Fillable,
               Ability\Griddable,
               Ability\Highlightable,
               Ability\Legendable,
               Ability\Shadowable,
               Ability\Zoomable,
               Ability\Labelable,
               Ability\Lineable
{
    protected $library = 'flot';
    protected $typeNamespace = '\\Altamira\\Type\\Flot\\';
    
    protected $dateAxes = array('x'=>false, 'y'=>false);
    protected $zooming = false;
    protected $highlighting = false;
    protected $pointLabels = array();
    protected $labelSettings = array('location'=>'w','xpadding'=>'0','ypadding'=>'0');
    
    protected function generateScript()
    {
        $name = $this->chart->getName();
        $dataArrayJs = '[';
        
        $this->prepOpts($this->options['series']);
        
        $counter=0;
        foreach ($this->chart->getSeries() as $title=>$series) {
            
            $dataArrayJs .= $counter++ > 0 ? ', ' : '';
            
            $dataArrayJs .= '{';
            
            // associate Xs with Ys in cases where we need it
            $data = $series->getData();
            
            $oneDimensional = array_keys($data) == range(0, count($data)-1, 1);
            
            if (! empty($this->seriesLabels[$title]) ) {
                $labelCopy = $this->seriesLabels[$title];
            }

            foreach ($data as $key=>$val) { 
                foreach ($this->dateAxes as $axis=>$flag) { 
                    if ($flag) {
                        switch ($axis) {
                            case 'x':
                                
                                $date = \DateTime::createFromFormat('m/d/Y', $data[$key][0]);
                                $data[$key][0] = $date->getTimestamp() * 1000;
                                break;
                            case 'y':
                                $date = \DateTime::createFromFormat('m/d/Y', $data[$key][1]);
                                $data[$key][1] = $date->getTimestamp() * 1000;
                                break;
                        }
                    }
                }
                // update if date
                $val = $data[$key];

                if (is_array($val)) {
                    $data[$key] = array_slice($val, 0, 2);
                    if ( isset($this->types['default']) ) { 
                        if (   $this->types['default'] instanceOf \Altamira\Type\Flot\Pie
                            || $this->types['default'] instanceOf \Altamira\Type\Flot\Donut ) {
                        
                            $data[$key] = array('label' => $val[0], 'data' => $val[1]);
                            
                            
                        } else if ($this->types['default'] instanceOf \Altamira\Type\Flot\Bubble ) {
                            $bubblePoints = array_slice($val, 0, 3);
                            $bubblePoints[2] *= 10;
                            $data[$key] = $bubblePoints;
                        } 
                        
                    }
                        
                    
                    if (!empty($this->seriesLabels[$title])) {
                        $dataPoints = "{$val[0]},{$val[1]}";
                        $this->pointLabels[$dataPoints] = array_shift($labelCopy);
                    }
                } else {
                    $valueArray = array(($oneDimensional? $key+1 : $key), $val);
                    if (isset($this->types['default']) && $this->types['default'] instanceOf \Altamira\Type\Flot\Bar ) {
                        $baropts = $this->types['default']->getOptions();
                        if (isset($baropts['bars']['horizontal']) && $baropts['bars']['horizontal'] == true) {
                            $valueArray = array($val, $key);
                        }
                    }
                    $data[$key] = $valueArray;
                }
            };
            
            $dataArrayJs .= 'data: '.$this->makeJSArray($data);
            
            // TODO wrapped this in an isset(), is 'default' always supposed to be defined?
            if (isset($this->types['default']) && $this->types['default'] instanceOf \Altamira\Type\Flot\Bubble ) {
                // TODO somebody late night coding? originally: end(end($series->getData())))
                // end(end()) makes no sense!
                $var0=$series->getData();
                $var1=end($var0);
                $var2=end($var1);
                $dataArrayJs .= ', label: "' . str_replace('"', '\\"', $var2) . '"';
            }

            $this->prepOpts( $this->options['seriesStorage'][$title] );
            
            $opts = substr(json_encode($this->options['seriesStorage'][$title]), 1, -1);
            
            if (strlen($opts) > 2) {
                $dataArrayJs .= ',' . $opts;
            }
            
            $dataArrayJs .= '}';
        }
        
        
        $dataArrayJs .= ']';
        
        $optionsJs = ($js = $this->getOptionsJs()) ? ", {$js}" : ', {}';
        
        $extraFunctionCallString = implode("\n", $this->getExtraFunctionCalls($dataArrayJs, $optionsJs));
        
        return <<<ENDSCRIPT
jQuery(document).ready(function() {
    var placeholder = jQuery('#{$name}');
    var plot = jQuery.plot(placeholder, {$dataArrayJs}{$optionsJs});
    {$extraFunctionCallString}
});
        
ENDSCRIPT;
        
    }
    
    public function getExtraFunctionCalls($dataArrayJs, $optionsJs)
    {
        $extraFunctionCalls = array();
        
        if ($this->zooming) {
            $extraFunctionCalls[] = <<<ENDJS
placeholder.bind("plotselected", function (event, ranges) {
    jQuery.plot(placeholder, {$dataArrayJs},
      $.extend(true, {}{$optionsJs}, {
      xaxis: { min: ranges.xaxis.from, max: ranges.xaxis.to },
      yaxis: { min: ranges.yaxis.from, max: ranges.yaxis.to }
  }));
});
placeholder.on('dblclick', function(){ plot.clearSelection(); jQuery.plot(placeholder, {$dataArrayJs}{$optionsJs}); });
ENDJS;
        
        }
        // TODO wrapped in isset(), is useLabels always supposed to be defined?
        if (isset($this->useLabels) && $this->useLabels) {
            $seriesLabels = json_encode($this->pointLabels);
            
            $top = '';
            $left = '';
            $pixelCount = '15';
            
            for ( $i = 0; $i < strlen($this->labelSettings['location']); $i++ ) {
                switch ( $this->labelSettings['location'][$i] ) {
                    case 'n':
                        $top = '-'.$pixelCount;
                        break;
                    case 'e':
                        $left = '+'.$pixelCount;
                        break; 
                    case 's':
                        $top = '+'.$pixelCount;
                        break;
                    case 'w':
                        $left = '-'.$pixelCount;
                }
            }
            
            $paddingx = '-'.(isset($this->labelSettings['xpadding']) ? $this->labelSettings['xpadding'] : '0');
            $paddingy = '-'.(isset($this->labelSettings['ypadding']) ? $this->labelSettings['ypadding'] : '0');
            
            $extraFunctionCalls[] = <<<ENDJS
var pointLabels = {$seriesLabels};
            
$.each(plot.getData()[0].data, function(i, el){
    var o = plot.pointOffset({
        x: el[0], y: el[1]});
        $('<div class="data-point-label">' + pointLabels[el[0] + ',' + el[1]] + '</div>').css( {
            position: 'absolute',
            left: o.left{$left}{$paddingx},
            top: o.top-5{$top}{$paddingy},
            display: 'none',
            'font-size': '10px'
        }).appendTo(plot.getPlaceholder()).fadeIn('slow');
});
ENDJS;

        }
        
        if ($this->highlighting) {
            
            $labelPaddingX = 5;
            $labelPaddingY = 5;
            
            $formatPoints = "x + ',' + y";
            
            foreach ($this->dateAxes as $axis=>$flag) { 
                if ($flag) {
                    $formatPoints = str_replace($axis, "(new Date(parseInt({$axis}))).toLocaleDateString()",$formatPoints);
                }
            }

            $extraFunctionCalls[] =  <<<ENDJS
            
function showTooltip(x, y, contents) {
    $('<div id="flottooltip">' + contents + '</div>').css( {
        position: 'absolute',
        display: 'none',
        top: y + {$labelPaddingY},
        left: x + {$labelPaddingX},
        border: '1px solid #fdd',
        padding: '2px',
        'background-color': '#fee',
        opacity: 0.80
    }).appendTo("body").fadeIn(200);
}

var previousPoint = null;

placeholder.bind("plothover", function (event, pos, item) {
    if (item) {
        if (previousPoint != item.dataIndex) {
            previousPoint = item.dataIndex;
            
            $("#flottooltip").remove();
            var x = item.datapoint[0].toFixed(2),
                y = item.datapoint[1].toFixed(2);
            
            showTooltip(item.pageX, item.pageY,
                        {$formatPoints});
        }
    }
    else {
        $("#flottooltip").remove();
        previousPoint = null;            
    }
});
ENDJS;
            
        }
        
        return $extraFunctionCalls;
        
    }
    
    public function setAxisOptions($axis, $name, $value)
    {
        if(strtolower($axis) === 'x' || strtolower($axis) === 'y') {
            $axis = strtolower($axis) . 'axis';
    
            if (isset($this->nativeOpts[$axis][$name])) {
                $this->options[$axis][$name] = $value;
            } else {
                $key = 'axes.'.$axis.'.'.$name;

                if (isset($this->optsMapper[$key])) {
                    $this->setOpt($this->options, $this->optsMapper[$key], $value);
                }
                
                if ($name == 'formatString') {
                    $this->options[$axis]['tickFormatter'] = $this->getCallbackPlaceholder('function(val, axis){return "'.$value.'".replace(/%d/, val);}');
                }
                
            }
        }

        return $this;
    }

    protected function getTypeOptions(array $options)
    {
        $types = $this->types;
    
        if(isset($types['default'])) {
            $options = array_merge_recursive($options, $types['default']->getOptions());
        }
    
        if(isset($options['axes'])) {
            foreach($options['axes'] as $axis => $contents) {
                if(isset($options['axes'][$axis]['renderer']) && is_array($options['axes'][$axis]['renderer'])) {
                    $options['axes'][$axis]['renderer'] = $options['axes'][$axis]['renderer'][0];
                }
            }
        }
        
        return $options;
    }
    
    protected function getSeriesOptions(array $options)
    {
        $types = $this->types;
    
        if(isset($types['default'])) {
            array_merge_recursive($options['series'], $types['default']->getOptions());
        }
    
        $seriesOptions = array();
        // TODO wrapped in isset(), is seriesStorage always supposed to be defined?
        if (isset ($this->options['seriesStorage'])) {
            foreach($this->options['seriesStorage'] as $title => $opts) {
                if(isset($types[$title])) {
                    $type = $types[$title];
                    array_merge_recursive($opts, $type->getSeriesOptions());
                }
                $opts['label'] = $title;
                $seriesOptions[$title] = $opts;
            }
        }
        $options['seriesStorage'] = $seriesOptions;
        
        return $options;
    }
    
    public function getOptionsJS()
    {
        foreach ($this->optsMapper as $opt => $mapped)
        {
            if (($currOpt = $this->getOptVal($this->options, $opt)) && ($currOpt !== null)) {
                $this->setOpt($this->options, $mapped, $currOpt);
                $this->unsetOpt($this->options, $opt);
            }
        }
        
        $opts = $this->options;
        
        // stupid pie plugin
        if (!isset($opts['series']['pie']['show'])) {
            $opts = array_merge_recursive($opts, array('series'=>array('pie'=>array('show'=>false))));
        }

        unset($opts['seriesStorage']);
        unset($opts['seriesDefaults']);
        
        return $this->makeJSArray($opts);
    }
    
    // these are helper functions to transform jqplot options to flot
    private function getOptVal(array $opts, $option)
    {
        $ploded = explode('.', $option);
        $arr = $opts;
        $val = null;
        while ($curr = array_shift($ploded)) {
            if (isset($arr[$curr])) {
                if (is_array($arr[$curr])) {
                    $arr = $arr[$curr];
                } else {
                    return $arr[$curr];
                }
            } else {
                return null;
            }
        }
        return $arr;
    }
    
    private function setOpt(array &$opts, $mapperString, $val)
    {
        $ploded = explode('.', $mapperString);
        $arr = &$opts;
        while ($curr = array_shift($ploded)) {
            if (isset($arr[$curr])) {
                if (is_array($arr[$curr])) {
                    $arr = &$arr[$curr];
                } else {
                    $arr[$curr] = $val;
                }
            } else {
                $arr[$curr] = empty($ploded) ? $val : array();
                $arr = &$arr[$curr];
            }
        }
    }
    
    private function unsetOpt(array &$opts, $mapperString)
    {
        $ploded = explode('.', $mapperString);
        $arr = &$opts;
        while ($curr = array_shift($ploded)) {
            if (isset($arr[$curr])) {
                if (is_array($arr[$curr])) {
                    $arr = &$arr[$curr];
                } else {
                    unset($arr[$curr]);
                }
            }
        }
    }
    
    public function useHighlighting(array $opts = array('size'=>7.5))
    {
        $this->highlighting = true;
        
        $this->options['grid']['hoverable'] = true;
        $this->options['grid']['autoHighlight'] = true;
    
        return $this;
    }
    
    public function useCursor()
    {
        $this->options['cursor'] = array('show' => true, 'showTooltip' => true);
    
        return $this;
    }
    
    public function useDates($axis = 'x')
    {
        $this->dateAxes[$axis] = true;
        
        $this->options[$axis.'axis']['mode'] = 'time';
        $this->options[$axis.'axis']['timeformat'] = '%d-%b-%y';
        
        array_push($this->files, 'jquery.flot.time.js');
    
        return $this;
    }
    
    public function useZooming( array $options = array('mode'=>'xy') )
    {
        $this->zooming = true;
        $this->options['selection'] = array('mode' => $options['mode'] );
        $this->files[] = 'jquery.flot.selection.js';
    }
    
    public function setGrid(array $opts)
    {
        
        $gridMapping = array('on'=>'show', 
                             'background'=>'backgroundColor'
                            );
        
        foreach ($opts as $key=>$value) {
            if ( in_array($key, $this->nativeOpts['grid']) ) {
                $this->options['grid'][$key] = $value;
            } else if ( in_array($key, $gridMapping) ) {
                $this->options['grid'][$gridMapping[$key]] = $value;
            }
        }
        
        return $this;
        
    }
    
    public function setLegend(array $opts = array('on' => 'true', 
                                                  'location' => 'ne', 
                                                  'x' => 0, 
                                                  'y' => 0))
    {        
        $opts['on'] = isset($opts['on']) ? $opts['on'] : true;
        $opts['location'] = isset($opts['location']) ? $opts['location'] : 'ne';

        $legendMapper = array('on' => 'show',
                              'location' => 'position');
        
        foreach ($opts as $key=>$val) {
            if ( in_array($key, $this->nativeOpts['legend']) ) {
                $this->options['legend'][$key] = $val;
            } else if ( in_array($key, array_keys($legendMapper)) ) { 
                $this->options['legend'][$legendMapper[$key]] = $val;
            }
        }
        
        $margin = array(isset($opts['x']) ? $opts['x'] : 0, isset($opts['y']) ? $opts['y'] : 0);
        
        $this->options['legend']['margin'] = $margin;
        
        
        return $this;
    }
    
    public function setFill($series, $opts = array('use' => true,
                                                   'stroke' => false,
                                                   'color' => null,
                                                   'alpha' => null
                                                  ))
    {
        
        // @todo add a method of telling flot whether the series is a line, bar, point
        if (isset($opts['use']) && $opts['use'] == true) {
            $this->options['seriesStorage'][$series]['line']['fill'] = true;
            
            if (isset($opts['color'])) {
                $this->options['seriesStorage'][$series]['line']['fillColor'] = $opts['color'];
            }
        }
        
        return $this;
    }
    
    public function setShadow($series, $opts = array('use'=>true,
                                                     'angle'=>45,
                                                     'offset'=>1.25,
                                                     'depth'=>3,
                                                     'alpha'=>0.1))
    {
        
        if (isset($opts['use']) && $opts['use']) {
            $this->options['seriesStorage'][$series]['shadowSize'] = isset($opts['depth']) ? $opts['depth'] : 3;
        }
        
        return $this;
    }
    
    public function useSeriesLabels( \Altamira\Series $series, array $labels = array() )
    {
        $this->useLabels = true;
        $this->seriesLabels[$series->getTitle()] = $labels;
    }
    
    public function setSeriesLabelSetting( \Altamira\Series $series, $name, $value )
    {
        // jqplot supports this, but we're just going to do global settings. overwrite at your own peril.
        $this->labelSettings[$name] = $value;
    }
    
    public function setSeriesLineWidth( \Altamira\Series $series, $value )
    {
        $this->options['seriesStorage'][$series->getTitle()]['lines'] = ( isset($this->options['seriesStorage'][$series->getTitle()]['lines'])
                                                               ? $this->options['seriesStorage'][$series->getTitle()]['lines']
                                                               : array() )
                                                               + array('lineWidth'=>$value);
        
        return $this;
    }
    
    public function setSeriesShowLine( \Altamira\Series $series, $bool )
    {
        $this->options['seriesStorage'][$series->getTitle()]['lines'] = ( isset($this->options['seriesStorage'][$series->getTitle()]['lines'])
                                                               ? $this->options['seriesStorage'][$series->getTitle()]['lines']
                                                               : array() )
                                                               + array('show'=>$bool);
        return $this;
    }
    
    public function setSeriesShowMarker( \Altamira\Series $series, $bool )
    {
        $this->options['seriesStorage'][$series->getTitle()]['points'] = ( isset($this->options['seriesStorage'][$series->getTitle()]['points'])
                                                                ? $this->options['seriesStorage'][$series->getTitle()]['points']
                                                                : array() )
                                                                + array('show'=>$bool);
        return $this;
    }
    
    public function setSeriesMarkerStyle( \Altamira\Series $series, $value )
    {
        // jqplot compatibility preprocessing
        $value = str_replace('filled', '', $value);
        $value = strtolower($value);
        
        if (! in_array('jquery.flot.symbol.js', $this->files)) {
            $this->files[] = 'jquery.flot.symbol.js';
        }
        
        $this->options['seriesStorage'][$series->getTitle()]['points'] = ( isset($this->options['seriesStorage'][$series->getTitle()]['points'])
                                                                ? $this->options['seriesStorage'][$series->getTitle()]['points']
                                                                : array() )
                                                                + array('symbol'=>$value);
        
        return $this;    
    }
    
    public function setSeriesMarkerSize( \Altamira\Series $series, $value )
    {
        $this->options['seriesStorage'][$series->getTitle()]['points'] = ( isset($this->options['seriesStorage'][$series->getTitle()]['points'])
                ? $this->options['seriesStorage'][$series->getTitle()]['points']
                : array() )
                + array('radius'=>(int) ($value / 2));
        
        return $this;
    }
    
    public function setAxisTicks($axis, $ticks)
    {
        if ( in_array($axis, array('x', 'y') ) ) {
            
            $isString = false;
            $alternateTicks = array();
            $cnt = 1;
            
            foreach ($ticks as $tick) {
                if (!(ctype_digit($tick) || is_int($tick))) {
                    $isString = true; 
                }
                $alternateTicks[] = array($cnt++, $tick);
            }
            
            $this->options[$axis.'axis']['ticks'] = $isString ? $alternateTicks : $ticks;
        }
        
        return $this;
    }
    
    public function prepOpts( &$opts = array() )
    {
        if (!(isset($this->types['default']) && $this->types['default'] instanceOf \Altamira\Type\Flot\Bubble)) {
            if ( (! isset($this->options['series']['points'])) && (!isset($opts['points']) || !isset($opts['points']['show'])) ) {
                // show points by default
                $opts['points'] = (isset($opts['points']) ? $opts['points'] : array())
                                + array('show'=>true);
            }
            
            if ( (! isset($this->options['series']['lines'])) && (!isset($opts['lines']) || !isset($opts['lines']['show'])) ) {
                // show lines by default
                $opts['lines'] = (isset($opts['lines']) ? $opts['lines'] : array())
                + array('show'=>true);
            }
        }
    }

    // maps jqplot-originating option data structure to flot
    private $optsMapper = array('axes.xaxis.tickInterval' => 'xaxis.tickSize',
                                'axes.xaxis.min'          => 'xaxis.min',
                                'axes.xaxis.max'          => 'xaxis.max',
                                'axes.xaxis.mode'         => 'xaxis.mode',
                                'axes.xaxis.ticks'        => 'xaxis.ticks',
            
                                'axes.yaxis.tickInterval' => 'yaxis.tickSize',
                                'axes.yaxis.min'          => 'yaxis.min',
                                'axes.yaxis.max'          => 'yaxis.max',
                                'axes.yaxis.mode'         => 'yaxis.mode',
                                'axes.yaxis.ticks'        => 'yaxis.ticks',
                                
                                'legend.show'             => 'legend.show',
                                'legend.location'         => 'legend.position',
                                'seriesColors'            => 'colors',
                                );
    
    
    // api-native functionality
    private $nativeOpts = array('legend' => array(  'show'=>null,
                                                    'labelFormatter'=>null,
                                                    'labelBoxBorderColor'=>null,
                                                    'noColumns'=>null,
                                                    'position'=>null,
                                                    'margin'=>null,
                                                    'backgroundColor'=>null,
                                                    'backgroundOpacity'=>null,
                                                    'container'=>null),

                                'xaxis' => array(   'show'=>null,
                                                    'position'=>null,
                                                    'mode'=>null,
                                                    'color'=>null,
                                                    'tickColor'=>null,
                                                    'min'=>null,
                                                    'max'=>null,
                                                    'autoscaleMargin'=>null,
                                                    'transform'=>null,
                                                    'inverseTransform'=>null,
                                                    'ticks'=>null,
                                                    'tickSize'=>null,
                                                    'minTickSize'=>null,
                                                    'tickFormatter'=>null,
                                                    'tickDecimals'=>null,
                                                    'labelWidth'=>null,
                                                    'labelHeight'=>null,
                                                    'reserveSpace'=>null,
                                                    'tickLength'=>null,
                                                    'alignTicksWithAxis'=>null,
                                                ),
                                                
                                'yaxis' => array(   'show'=>null,
                                                    'position'=>null,
                                                    'mode'=>null,
                                                    'color'=>null,
                                                    'tickColor'=>null,
                                                    'min'=>null,
                                                    'max'=>null,
                                                    'autoscaleMargin'=>null,
                                                    'transform'=>null,
                                                    'inverseTransform'=>null,
                                                    'ticks'=>null,
                                                    'tickSize'=>null,
                                                    'minTickSize'=>null,
                                                    'tickFormatter'=>null,
                                                    'tickDecimals'=>null,
                                                    'labelWidth'=>null,
                                                    'labelHeight'=>null,
                                                    'reserveSpace'=>null,
                                                    'tickLength'=>null,
                                                    'alignTicksWithAxis'=>null,
                                                ),
                                                
                                 'xaxes' => null,
                                 'yaxes' => null,

                                 'series' => array(
                                                    'lines' => array('show'=>null, 'lineWidth'=>null, 'fill'=>null, 'fillColor'=>null),
                                                    'points'=> array('show'=>null, 'lineWidth'=>null, 'fill'=>null, 'fillColor'=>null),
                                                    'bars' => array('show'=>null, 'lineWidth'=>null, 'fill'=>null, 'fillColor'=>null),
                                                  ),
                                                  
                                 'points' => array('radius'=>null, 'symbol'=>null),
                                 
                                 'bars' => array('barWidth'=>null, 'align'=>null, 'horizontal'=>null),
                                 
                                 'lines' => array('steps'=>null),
                                
                                 'shadowSize' => null,                                
                                
                                 'colors' => null,
                                 
                                 'grid' =>  array(  'show'=>null,
                                                    'aboveData'=>null,
                                                    'color'=>null,
                                                    'backgroundColor'=>null,
                                                    'labelMargin'=>null,
                                                    'axisMargin'=>null,
                                                    'markings'=>null,
                                                    'borderWidth'=>null,
                                                    'borderColor'=>null,
                                                    'minBorderMargin'=>null,
                                                    'clickable'=>null,
                                                    'hoverable'=>null,
                                                    'autoHighlight'=>null,
                                                    'mouseActiveRadius'=>null
                                                )

                                 
                                );
    
}
