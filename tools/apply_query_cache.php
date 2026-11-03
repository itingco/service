<?php
/** Changes only exact call sites; idempotent and backs up original files. */
$root=dirname(__DIR__);
$updates=[
 'app/Services/SalesDashboardService.php'=>[
  '$this->dataSource->fetch($source, $startDate, $endDate)'=>
  '\\App\\Support\\CachedSalesQuery::fetch($this->dataSource, $source, $startDate, $endDate)',
 ],
 'app/Services/CustomerAnalysisService.php'=>[
  '$this->salesDataSource->fetch($source, $startDate, $endDate)'=>
  '\\App\\Support\\CachedSalesQuery::fetch($this->salesDataSource, $source, $startDate, $endDate)',
 ],
];
foreach($updates as $path=>$subs){
 $file=$root.'/'.$path;$code=file_get_contents($file);
 if($code===false) throw new RuntimeException("Missing $file");
 $new=$code;
 foreach($subs as $from=>$to){
  if(str_contains($new,$to)) continue;
  if(!str_contains($new,$from)) throw new RuntimeException("Expected expression not found in $path. No change applied.");
  $new=str_replace($from,$to,$new);
 }
 if($code!==$new){
  if(!file_exists($file.'.before-sales-cache.bak')) copy($file,$file.'.before-sales-cache.bak');
  file_put_contents($file,$new);echo "Updated $path\n";
 }
}
