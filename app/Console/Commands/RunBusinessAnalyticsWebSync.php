<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RunBusinessAnalyticsWebSync extends Command
{
    protected $signature = 'analytics:web-sync {job : ID business_analytics_web_sync_jobs}';
    protected $description = 'Internal wrapper untuk menjalankan analytics:sync --months=36 dari dashboard';

    public function handle(): int
    {
        $id=max(1,(int)$this->argument('job')); $db=DB::connection('sqlite_targets'); $job=$db->table('business_analytics_web_sync_jobs')->where('id',$id)->first();
        if(!$job){$this->error('Job tidak ditemukan.');return self::FAILURE;}
        try {
            $code=Artisan::call('analytics:sync',['--months'=>36]);
            $output=Artisan::output(); if(trim($output)!=='') $this->line($output);
            $db->table('business_analytics_web_sync_jobs')->where('id',$id)->update(['status'=>$code===0?'COMPLETED':'COMPLETED_WITH_ERRORS','finished_at'=>now(),'message'=>$code===0?'72 langkah sync selesai.':'Sync selesai dengan satu atau lebih kegagalan.','updated_at'=>now()]);
            return $code===0?self::SUCCESS:self::FAILURE;
        } catch(Throwable $e){$db->table('business_analytics_web_sync_jobs')->where('id',$id)->update(['status'=>'FAILED','finished_at'=>now(),'message'=>mb_substr($e->getMessage(),0,2000),'updated_at'=>now()]);$this->error($e->getMessage());return self::FAILURE;}
    }
}
