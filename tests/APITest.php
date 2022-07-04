<?php

use Infira\MeritAktiva\API;
use Infira\MeritAktiva\APIResult;
use PHPUnit\Framework\TestCase;

class APITest extends TestCase {
	
	private function getApi(): API {
		return new API( $_ENV['MERIT_API_ID'], $_ENV['MERIT_API_KEY'], $_ENV['MERIT_API_COUNTRY'] );
	}
	
	public function testGetSalesInvoicesIsApiResult() {
		$api = $this->getApi();
		
		$invoices = $api->getSalesInvoices( '-3 month', 'today' );
		
		$this->assertInstanceOf( APIResult::class, $invoices );
	}
	
	
	public function testGetSalesInvoicesReturnsArray() {
		$api = $this->getApi();
		
		$invoices = $api->getSalesInvoices( '-3 month', 'today' );
		
		$this->assertIsArray(  $invoices->getRaw() );
	}
    
    
    public function testGetItemByCodeReturnsItem(){
        $api = $this->getApi();
        
        $result = $api->getItemByCode($_ENV['MERIT_DEFAULT_ITEM_CODE']);
        
        $item = current($result->getRaw());
        
        $this->assertEquals($_ENV['MERIT_DEFAULT_ITEM_CODE'], $item->Code );
    }
}
