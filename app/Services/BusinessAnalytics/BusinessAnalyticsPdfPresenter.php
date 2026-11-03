<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class BusinessAnalyticsPdfPresenter
{
    public function money(float|int|null $value): string
    {
        return $value === null ? '-' : 'Rp '.number_format((float)$value, 0, ',', '.');
    }

    public function number(float|int|null $value): string
    {
        return $value === null ? '-' : number_format((float)$value, 0, ',', '.');
    }

    public function percent(?float $value): string
    {
        return $value === null ? '-' : number_format($value * 100, 1, ',', '.').'%';
    }

    /** @param array<string,float|int> $trend @param array<int,array<string,mixed>> $forecast */
    public function trendSvg(array $trend, array $forecast, int $width = 900, int $height = 260): string
    {
        $width=max(320,$width); $height=max(140,$height);
        $padL=54; $padR=20; $padT=20; $padB=42;
        $points=[];
        foreach($trend as $period=>$value){ $points[]=['period'=>(string)$period,'value'=>(float)$value,'type'=>'actual']; }
        foreach($forecast as $row){ if(isset($row['period'],$row['forecast'])) $points[]=['period'=>(string)$row['period'],'value'=>(float)$row['forecast'],'type'=>'forecast']; }
        if($points===[]) return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'"></svg>';
        $values=array_column($points,'value'); $max=max(1.0,max($values)); $min=min(0.0,min($values)); $range=max(1.0,$max-$min);
        $plotW=$width-$padL-$padR; $plotH=$height-$padT-$padB; $count=count($points); $step=$count>1?$plotW/($count-1):0.0;
        $coords=[];
        foreach($points as $i=>$p){ $x=$padL+$step*$i; $y=$padT+$plotH-(($p['value']-$min)/$range*$plotH); $coords[]=['x'=>$x,'y'=>$y,'type'=>$p['type'],'period'=>$p['period']]; }
        $actual=[];$forecastPts=[];
        foreach($coords as $i=>$c){ if($c['type']==='actual'){$actual[]=$c['x'].','.$c['y']; if($i===count($trend)-1 && isset($coords[$i+1])) $forecastPts[]=$c['x'].','.$c['y'];} else {$forecastPts[]=$c['x'].','.$c['y'];} }
        $esc=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_XML1,'UTF-8');
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">';
        $svg.='<rect x="0" y="0" width="'.$width.'" height="'.$height.'" fill="#ffffff"/>';
        for($i=0;$i<=4;$i++){ $y=$padT+$plotH*$i/4; $v=$max-$range*$i/4; $svg.='<line x1="'.$padL.'" y1="'.$y.'" x2="'.($width-$padR).'" y2="'.$y.'" stroke="#e5e7eb" stroke-width="1"/>'; $svg.='<text x="4" y="'.($y+4).'" font-size="9" fill="#667085">'.number_format($v/1000000,1,',','.').'M</text>'; }
        if($actual!==[]) $svg.='<polyline points="'.implode(' ',$actual).'" fill="none" stroke="#2563eb" stroke-width="2.5"/>';
        if(count($forecastPts)>1) $svg.='<polyline points="'.implode(' ',$forecastPts).'" fill="none" stroke="#f43f5e" stroke-width="2.5" stroke-dasharray="6,4"/>';
        $labelEvery=max(1,(int)ceil($count/10));
        foreach($coords as $i=>$c){ if($i%$labelEvery!==0 && $i!==$count-1) continue; $svg.='<text x="'.$c['x'].'" y="'.($height-20).'" text-anchor="middle" font-size="8" fill="#667085">'.$esc($c['period']).'</text>'; }
        $svg.='<text x="'.$padL.'" y="12" font-size="9" fill="#2563eb">Actual</text><text x="'.($padL+55).'" y="12" font-size="9" fill="#f43f5e">Forecast</text>';
        return $svg.'</svg>';
    }
}
