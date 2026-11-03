<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SalesResearchInsightService;
use PHPUnit\Framework\TestCase;

final class SalesResearchInsightServiceTest extends TestCase
{
    public function test_it_classifies_product_demand_and_rd_actions(): void
    {
        $current = [
            $this->row('A1','Alpha','A','ONLINE','C1',600,5),
            $this->row('A1','Alpha','A','ONLINE','C2',400,5),
            $this->row('A1','Alpha','A','JAKARTA','C3',200,5),
            $this->row('B1','Baru','B','JAKARTA','C1',500,0),
            $this->row('C1','Turun','B','MEDAN','C9',200,25),
            $this->row('D1','Keluar','','MEDAN','C8',100,30,true,true),
        ];
        $previous = [
            $this->row('A1','Alpha','A','ONLINE','C1',800,0),
            $this->row('C1','Turun','B','MEDAN','C9',500,10),
            $this->row('E1','Hilang','A','JAKARTA','C7',300,0),
        ];

        $result = (new SalesResearchInsightService())->build($current, $previous);
        $signals = array_column($result->productSignals, null, 'item_code');

        self::assertSame('SCALE UP', $signals['A1']['signal']);
        self::assertSame('PERMINTAAN BARU', $signals['B1']['signal']);
        self::assertSame('EVALUASI', $signals['C1']['signal']);
        self::assertSame('FASE KELUAR', $signals['D1']['signal']);
        self::assertSame('PERMINTAAN HILANG', $signals['E1']['signal']);
        self::assertSame('ONLINE', $signals['A1']['primary_line']);
        self::assertSame(3, $signals['A1']['customer_count']);
        self::assertSame('C1', $result->discountWatch[0]['item_code']);
    }

    private function row(string $code,string $name,string $grade,string $line,string $customer,float $sales,float $discount,bool $discontinued=false,bool $clearance=false): array
    {
        return ['item_code'=>$code,'item_name'=>$name,'grade'=>$grade,'line'=>$line,'customer_code'=>$customer,'line_total'=>$sales,'quantity'=>1,'discount'=>$discount,'discount2'=>0,'discontinued'=>$discontinued,'clearance'=>$clearance];
    }
}
