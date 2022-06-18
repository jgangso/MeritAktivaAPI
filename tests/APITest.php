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
		
		$invoices = $api->getSalesInvoices( '-1 week', 'today' );
		
		$this->assertInstanceOf( APIResult::class, $invoices );
	}
	
	
	public function testGetSalesInvoicesReturnsArray() {
		$api = $this->getApi();
		
		$invoices = $api->getSalesInvoices( '-1 week', 'today' );
		
		$this->assertIsArray(  $invoices->getRaw() );
	}
}
