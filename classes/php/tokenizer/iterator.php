<?php

namespace mageekguy\atoum\php\tokenizer;

use
	\mageekguy\atoum\exceptions,
	\mageekguy\atoum\php\tokenizer\iterator
;

class iterator extends iterator\value
{
	protected $key = null;
	protected $size = 0;
	protected $values = array();
	protected $parent = null;
	protected $excludedValues = array();
	protected $innerIteratorKey = null;

	public function __toString()
	{
		$string = '';

		if (sizeof($this) > 0)
		{
			foreach ($this as $value)
			{
				$string .= $value;
			}

			$this->rewind();
		}

		return $string;
	}

	public function valid()
	{
		return ($this->key !== null && $this->key >= 0 && $this->key < $this->size);
	}

	public function current()
	{
		$value = null;

		if ($this->valid() === true)
		{
			$value = current($this->values);

			if ($value instanceof self)
			{
				$value = $value->current();
			}
		}

		return $value;
	}

	public function key()
	{
		return $this->key < 0 || $this->key >= $this->size ? null : $this->key;
	}

	public function prev($offset = 1)
	{
		while ($offset && $this->valid() === true)
		{
			$currentKey = key($this->values);
			$currentValue = current($this->values);

			if ($currentValue instanceof self === false)
			{
				prev($this->values);
				$currentValue = current($this->values);
			}
			else
			{
				$currentValue->prev();

				if ($currentValue->valid() === false)
				{
					prev($this->values);
					$currentValue = current($this->values);
				}
			}

			while ($currentValue instanceof self && sizeof($currentValue) <= 0)
			{
				prev($this->values);
				$currentValue = current($this->values);
			}

			if ($currentValue instanceof self === true && key($this->values) !== $currentKey)
			{
				$currentValue->end();
			}

			if (in_array($this->current(), $this->excludedValues) === true)
			{
				$this->prev();
			}

			$this->key--;

			$offset--;
		}

		return $this;
	}

	public function next($offset = 1)
	{
		while ($offset > 0 && $this->valid() === true)
		{
			$currentKey = key($this->values);
			$currentValue = current($this->values);

			if ($currentValue instanceof self === false)
			{
				next($this->values);
				$currentValue = current($this->values);
			}
			else
			{
				$currentValue->next();

				if ($currentValue->valid() === false)
				{
					next($this->values);
					$currentValue = current($this->values);
				}
			}

			while ($currentValue instanceof self && sizeof($currentValue) <= 0)
			{
				next($this->values);
				$currentValue = current($this->values);
			}

			if ($currentValue instanceof self && key($this->values) !== $currentKey)
			{
				$currentValue->rewind();
			}

			if (in_array($this->current(), $this->excludedValues) === true)
			{
				$this->next();
			}

			$this->key++;

			$offset--;
		}

		return $this;
	}

	public function rewind()
	{
		if ($this->size > 0)
		{
			reset($this->values);

			$currentValue = current($this->values);

			while ($currentValue instanceof self && $currentValue->rewind()->valid() == false)
			{
				next($this->values);
				$currentValue = current($this->values);
			}

			$this->key = 0;

			if (in_array($this->current(), $this->excludedValues) === true)
			{
				$this->next();
			}
		}

		return $this;
	}

	public function end()
	{
		if ($this->size > 0)
		{
			end($this->values);

			$currentValue = current($this->values);

			while ($currentValue instanceof self && $currentValue->end()->valid() == false)
			{
				prev($this->values);
				$currentValue = current($this->values);
			}

			$this->key = $this->size - 1;

			if (in_array($this->current(), $this->excludedValues) === true)
			{
				$this->prev();
			}
		}

		return $this;
	}

	public function append(iterator\value $value)
	{
		$this->values[] = $value;

		if ($this->key === null)
		{
			$this->key = 0;
		}

		$size = 1;

		if ($value instanceof self)
		{
			if ($value->parent !== null)
			{
				throw new exceptions\runtime('Unable to append iterator, it has already a parent');
			}

			$size = sizeof($value);

			if ($size > 0)
			{
				$value->rewind();
			}

			$this->innerIteratorKey = $value->key();

			$value->parent = $this;
			$value->excludedValues = array_unique(array_merge($this->excludedValues, $value->excludedValues));
		}

		$this->size += $size;

		if ($size > 0)
		{
			$parent = $this->parent;

			while ($parent !== null)
			{
				$parent->size += $size;

				$parent = $parent->parent;
			}
		}

		return $this;
	}

	public function count()
	{
		return $this->size;
	}

	public function excludeValue($value)
	{
		if (in_array($value, $this->excludedValues) === false)
		{
			$this->excludedValues[] = $value;
		}

		return $this;
	}

	public function getExcludedValues()
	{
		return $this->excludedValues;
	}

	public function reset()
	{
		$this->key = null;
		$this->size = 0;
		$this->values = array();
		$this->parent = null;
		$this->excludedValues = array();

		return $this;
	}

	public function getValue()
	{
		$currentValue = current($this->values);

		return ($currentValue instanceof self === false ? null : $currentValue);
	}

	public function getParent()
	{
		return $this->parent;
	}

	public function seek($key)
	{
		if ($key > sizeof($this) / 2)
		{
			$this->end();
		}
		else if ($this->valid() === false)
		{
			$this->rewind();
		}

		if ($key > $this->key)
		{
			$this->next($key - $this->key);
		}
		else
		{
			$this->prev($this->key - $key);
		}

		return $this;
	}
}

?>
