<?php

/**
* Validator
*/
class Validator
{
	/**
	 * form validation rules and options
	 *
	 * @var array
	 **/
	public $options = array();

	/**
	 * whether or not the form has been submitted
	 *
	 * @var bool
	 **/
	public $has_post_data = FALSE;
	
	/**
	 * container for values that have passed validation
	 *
	 * @var array
	 **/
	public $clean  = array();
	
	/**
	 * container for values that have been html escaped
	 *
	 * @var array
	 **/
	public $html  = array();

	/**
	 * container for form error messages
	 *
	 * @var array
	 **/
	public $errors = array();
	
	function __construct()
	{
		// Has the form been submitted?
		$this->has_post_data = ( $_SERVER['REQUEST_METHOD'] === 'POST' ) ? TRUE : FALSE;
		if ($this->has_post_data)
		{
			$this->clean_request($_POST);
		}
	}
	
	public function clean_request($data)
	{
		if (get_magic_quotes_gpc())
		{
			$this->clean = $this->array_map_recursive('stripslashes', $data);
		}
		else
		{
			$this->clean = $data;
		}
			
		$this->html = $this->array_map_recursive('html_escape', $this->clean);
	}
	
	protected function array_map_recursive($function, $data)
    {
        foreach ( $data as $i => $item )
        {
			if (is_array($item))
			{
				$data[$i] = array_map_recursive($function, $item);
			}
			else
			{
				if (method_exists(__CLASS__, $function))
				{
					$data[$i] = $this->$function($item);
				}
				else 
				{
					$data[$i] = $function($item);
				}
			}
        }
        return $data ;
    }
    
	/**
	 * check all post data to see if any of the data needs to be validated
	 *
	 * @param array|$options array of rules and messages for the form
	 * @return void
	 **/
	public function validate($options)
	{
		$this->options = $options;
		
		if ($this->has_post_data)
		{
			foreach ($this->options['rules'] as $field_name => $rules)
			{
				$this->apply_rules($field_name, $rules);
			}
		}
	}
	
	/**
	 * process validation urls for an individual field. set errors and add to $this->clean
	 *
	 * @param array|$options array of rules and messages for the form
	 * @return $this
	 **/
	protected function apply_rules($field_name, $rules)
	{
		foreach ($rules as $method => $args)
		{
			if (method_exists(__CLASS__, $method) || function_exists($method))
			{
				if (isset($_POST[$field_name])) 
				{
					$value = $_POST[$field_name];
				}
				else
				{
					$value = '';
				}
				
				if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
					// this is only possible in php > 5.3.0
					if (is_callable($args))
					{
						$args = $args();
					}
				}
				
				if (method_exists(__CLASS__, $method)) 
				{
					# code...
					$valid = $this->$method($args, $value, $field_name);
				}
				else
				{
					$valid = $method($args, $value, $field_name);
				}
				
				if (TRUE === $valid)
				{
					// Add field to clean if it is valid
					$this->clean[$field_name] = $value;
				}
				else
				{
					$message = $this->options['messages'][$field_name][$method];
					
					
					if (is_callable($message))
					{
						$message = $message($args, $value, $field_name);
					}

					// Set error message
					$this->errors[$field_name] = $message;
					
					// stop processing rules
					break;
				}
			}
			else
			{
				throw new Exception('Unknown validation rule: ' . $method);
			}
		}
		
		return $this;
	}
	
	
	/**
	 * html encode field value
	 *
	 * @param string|$name the name of the field to html encode
	 * @return $string
	 **/
	public function html_escape($value)
	{
		return htmlentities($value, ENT_QUOTES, 'UTF-8');
	}
	
	/**
	 * get html encoded field value
	 *
	 * @param string|$name the name of the field to html encode
	 * @return $string
	 **/
	public function html($name)
	{
		return (isset($this->html[$name])) ? $this->html[$name] : '';
	}
	
	/**
	 * figure out if a checkbox or radio button is checked
	 *
	 * @param string|$name the name of the field to html encode
	 * @param string|$value the value to compare against
	 * @return $string
	 **/
	public function checked($name, $value)
	{
		if (isset($this->clean[$name]) && $this->clean[$name] == $value) 
		{
			return 'checked="checked"';
		}
	}
	
	/**
	 * get error message for a field
	 *
	 * @param string|$name the name of the field to html encode
	 * @return $string
	 **/
	public function get_error($name, $id = '')
	{
		$id = (empty($id)) ? $name : $id;
		
		if (isset($this->errors[$name]))
		{
			return '<label for="' . $id . '" class="error">' . $this->errors[$name] . '</label>';
		}
		else
		{
			return '';
		}
	}
	
	/**
	 * get classes for a field
	 *
	 * @param string|$name the name of the field
	 * @return $string
	 **/
	public function get_classes($name)
	{
		$classes = array();
		
		if (isset($this->errors[$name]))
		{
			$classes[] = 'error';
		}
		
		return implode(' ', $classes);
	}
	
	/**
	 * is the form valid
	 *
	 * @return $bool TRUE if the form is valid, FALSE if not
	 **/
	public function form_valid()
	{
		return ($this->has_post_data && FALSE === $this->has_errors()) ? TRUE : FALSE;
	}
	
	/**
	 * whether or not the form has errors
	 *
	 * @return $bool TRUE if the form has errors, FALSE if not
	 **/
	public function has_errors()
	{
		return (count($this->errors) > 0) ? TRUE : FALSE;
	}
	
	/**
	 * required, the field must contain a value
	 *
	 * @param mixed|$args arguments to determine if the field is required
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function required($args, $value)
	{
		if (TRUE === $args)
		{
			return ('' == $value) ? FALSE : TRUE;
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * minlength, the minimum length a field can be
	 *
	 * @param mixed|$args arguments to determine if the field is required
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function minlength($length, $value)
	{
		return (strlen($value) < $length) ? FALSE : TRUE;
	}
	
	/**
	 * maxlength, the maximum length a field can be
	 *
	 * @param integer|$length the maximum lenght the field should be
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function maxlength($length, $value)
	{
		return (strlen($value) > $length) ? FALSE : TRUE;
	}
	
	/**
	 * rangelength, must be between two values
	 *
	 * @param array|$range the two integer values the field should be between
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function rangelength($range, $value)
	{
		return ($this->minlength($range[0], $value) && $this->maxlength($range[1], $value)) ? TRUE : FALSE;
	}
	
	/**
	 * length, must be exactly the specified length
	 *
	 * @param integer|$length how long the field should be
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function length($length, $value)
	{
		return (strlen($value) == $length) ? TRUE : FALSE;
	}

	/**
	 * equalto, must be equal to the specified field
	 *
	 * @param string|$name the name of the field the field must be equal to
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function equalto($name, $value)
	{
		return ($_POST[$name] == $value) ? TRUE : FALSE;
	}

	/**
	 * email, the field must be a valid email address
	 *
	 * @param mixed|$args arguments to determine if the field is required
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function email($args, $email)
	{
		// from http://dev.kohanaframework.org/projects/kohana2/repository/entry/trunk/system/helpers/valid.php#L59
		return (bool) preg_match('/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD', (string) $email);
	}

	/**
	 * number, the field must be a number
	 *
	 * @param string value to check
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function number($args, $value)
	{
		return (bool) preg_match('/^-?(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d+)?$/', $value);
	}

	/**
	 * digits, the field must only contain digits
	 *
	 * @param string value to check
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function digits($args, $value)
	{
		return (bool) preg_match('/^\d+$/', (string) $value);
	}

	/**
	 * mindigits, the field must contain at least the specified number of digits 
	 * excluding other characters
	 *
	 * @param $min the minimum numer of digits
	 * @return bool true if it passes validation false if it fails
	 **/
	protected function mindigits($min, $value)
	{
		$numbers = preg_replace("/[^\d.]/", "", $value);
		return $this->minlength($min, $numbers);
	}
}
