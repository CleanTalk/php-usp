<?php

namespace Cleantalk\Common;

class Storage implements \IteratorAggregate{

	use \Cleantalk\Templates\FluidInterface;
	use \Cleantalk\Templates\Storage;

	public function getIterator()
	{
		return new \ArrayIterator($this->storage);
	}

}