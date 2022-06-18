<?php

namespace Infira\MeritAktiva;
class APIResult
{
	private $data = "";
	/**
	 * @var int|null
	 */
	private int $status;
	
	/**
	 * @param array $response
	 */
	public function __construct( array $response )
	{
		$this->status = $response['status'] ?? null;
		$this->data   = $response['data'] ?? null;
	}
	
	public function isError(): bool {
		return 200 !== $this->status;
	}
	
	public function getRaw()
	{
		return $this->data;
	}
	
	public function getError()
	{
		return $this->data;
	}
}
