<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Base Form Class
 *
 * A class that easily allows developers to create EE form tags.
 * 
 * @subpackage	Libraries
 * @author		Justin Kimbrell
 * @copyright	Copyright (c) 2012, Justin Kimbrell
 * @link 		http://www.objectivehtml.com/libraries/base_form
 * @version		1.1.7
 * @build		20120212
 */

if(!class_exists('Base_form'))
{
	class Base_form {
		
		public $action					= '';
		public $additional_params		= array('novalidate', 'onsubmit');
		public $class					= '';
		public $hidden_fields			= array();
		public $error_handling			= FALSE;
		public $errors					= array();
		public $field_errors 			= array();
		public $id						= '';
		public $name					= '';	
		public $prefix					= '';
		public $rules					= array();
		public $reserved_terms  		= array('', '_min', '_max', '_like');
		public $return					= FALSE;
		public $required				= '';
		public $secure_action			= FALSE;
		public $secure_return			= FALSE;
		public $tagdata					= '';
			
		public function __construct()
		{
			$this->EE =& get_instance();
			
			$this->return 	= $this->current_url();
			$this->tagdata 	= $this->EE->TMPL->tagdata;
		}
		
		public function open($hidden_fields = array(), $fields = FALSE, $entry = FALSE)
		{		
			$this->action			= empty($this->action) ? $this->param('action', $this->return) : $this->action;
		
			$this->class			= $this->param('class', $this->class);
			$this->error_handling 	= $this->param('error_handling', $this->error_handling);
			$this->hidden_fields	= array_merge($this->hidden_fields, $hidden_fields);
			$this->id				= $this->param('id', $this->id);
			$this->name				= $this->param('name', $this->name);
			$this->prefix			= $this->param('prefix', $this->prefix);
			$this->required 		= $this->param('required', $this->required);
			$this->required			= $this->required ? explode('|', $this->required) : FALSE;
			$this->rules 			= $this->param('rules', $this->rules);
			$this->return 			= $this->param('return', $this->return);
			$this->secure_action 	= $this->param('secure_action', $this->secure_action, TRUE);
			$this->secure_return 	= $this->param('secure_return', $this->secure_return, TRUE);
			
			if($this->EE->TMPL->tag_data[0]['params'])
			{
				foreach($this->EE->TMPL->tag_data[0]['params'] as $param => $rule)
				{
					if(preg_match("/^(rules:)/", $param, $matches))
					{
						$this->set_rule($param, $rule);
					}
				}
			}
			
			$hidden_fields  = array_merge($this->hidden_fields, array(
				'XID'	   => '{XID_HASH}',
				'site_url' => $this->param('site_url') ? $this->param('site_url') : $this->EE->config->item('site_url'),
				'required' 		=> $this->required,
				'secure_return' => $this->secure_return,
				'return'		=> $this->return
			));
			
			if(count($this->rules) > 0)
			{
				foreach($this->rules as $param => $rule)
				{	
					$hidden_fields['rule['.$param.']'] = $rule;
				}	
			}
						
			$params = array(
				'method' => 'post',
				'class'	 => $this->class,
				'id'	 => $this->id,
				'name'	 => $this->name
			);
					
			foreach($this->additional_params as $param)
			{
				if($this->param($param))
				{
					$params[$param] = $this->param($param);
				}
			}
			
			$this->validate();
			
			$errors = array(
				array(
					'errors'			  => array(array()),
					'total_errors'		  => count($this->field_errors) + count($this->errors),
					'field_errors' 		  => array(array()),
					'total_field_errors'  => 0,
					'global_errors'		  => array(array()),
					'total_global_errors' => 0
				)
			);
			
			$post_vars   = array();
			
			foreach($_POST as $post_field => $post_value)
			{
				$post = $this->EE->input->post($post_field);
				
				if($post)
				{	
					if(!is_array($post))
					{
						$post_vars['post:'.$post_field] = $post;
					}
					else
					{
						foreach($post as $post_index => $post_array)
						{
							if(!empty($post_array))
							{
								$post_vars['post:'.$post_field][0] = $post;
							}
						}
					}
				}
				else
				{
					$post_vars['post:'.$post_field] = NULL;
				}
			}
					
			if($fields && $entry)
			{
				$this->tagdata = $this->parse_fields($fields, $entry);				
			}
					
			$this->tagdata = $this->parse(array($post_vars));
					
			$errors = array();
			
			if(count($this->field_errors) > 0)
			{
				$x = 0;
				
				foreach($this->field_errors as $field => $error)
				{
					$errors[0]['field_errors'][$x] 		= array('error' => $error);
					$errors[0]['field_error:'.$field]   = $error;
					$x++;
				}
				
				$errors[0]['total_field_errors'] = count($this->field_errors);
			}
			
			if(count($this->errors) > 0)
			{
				$x = 0;
				
				foreach($this->errors as $error)
				{
					$errors[0]['global_errors'][$x]	= array('error' => $error);
					$x++;
				}
				
				$errors[0]['total_global_errors'] = count($this->errors);
			}
						
			$this->tagdata = $this->parse($errors);
				
			$this->EE->load->helper('form');
			$this->EE->load->helper('url');
			
			if(!preg_match("/(http|https|ftp|ftps)\:\/\/?/", $this->action, $mathes))
			{
				$this->action = rtrim($this->current_url(FALSE), '/') . '/' . ltrim($this->action, '/');
			}
			
			return form_open($this->action, $params, $hidden_fields) . $this->tagdata . '</form>';
		}
		
		public function set_rule($field_name, $rule)
		{
			$field_name = str_replace('rules:', '', $field_name);
			
			if(isset($this->rules[$field_name]))
			{
				$rule = rtrim($rule, '|') . '|' . $this->rules[$field_name];
			}
			
			$this->rules[$field_name] = $rule;
		}
		
		public function set_error($message)
		{
			$this->errors[] = $message;
		}
		
		public function set_field_error($field, $message)
		{
			$this->field_errors[$field] = $message;
		}
		
		public function parse_fields($field_data, $entry_data, $prefix = '')
		{
			if(!isset($entry_data[0]))
			{
				$entry_array = array($entry_data);
			}
			else
			{
				$entry_array = $entry_data;
			}
			
			foreach($entry_array as $entry_data)
			{
				$vars = array();
				
				foreach($field_data as $index => $row)
				{
					$field_name 		= $row['field_name'];
					$field_label 		= $row['field_label'];
					$field_instructions	= $row['field_instructions'];
					$field_type			= $row['field_type'];
					
					$vars[0][$field_name] = isset($entry_data[$prefix.$field_name]) ? $entry_data[$prefix.$field_name] : NULL;			
					$vars[0]['label:'.$field_name] = $field_label;
					$vars[0]['instructions:'.$field_name] = $field_instructions;
					$vars[0]['type:'.$field_name] = $field_type;
					
					if(!empty($row['field_list_items']))
					{
						foreach(explode("\n", $row['field_list_items']) as $option_index => $option)
						{
							$vars[0]['options:'.$field_name][$option_index] = array(
								'option_value'	=> $option,
								'option_name' 	=> $option,
								'selected'		=> $vars[0][$field_name] == $option ? 'selected="selected"' : NULL,
								'checked'		=> $vars[0][$field_name] == $option ? 'checked="checked"' : NULL
							);							
						}
					}
				}
			}
			
			return $this->parse($vars);
		}
				
		public function validate($required_fields = array(), $additional_rules = array())
		{
			$vars = array();
			
			$this->EE->load->library('form_validation');
			$this->EE->form_validation->set_error_delimiters('', '');
			
			$validate_fields = isset($_POST['required']) ? $_POST['required'] : $this->required;
			$validate_fields = !is_array($validate_fields) ? explode('|', $validate_fields) : $validate_fields;
			
			$required_fields = array_merge($required_fields, $validate_fields);
			
			foreach($required_fields as $field)
			{
				$this->EE->form_validation->set_rules($field, ucwords(str_replace(array('-', '_'), ' ', $field)), 'trim|required');
			}
			
			$rules = array_merge((isset($_POST['rule']) ? $_POST['rule'] : array()), $this->rules);
			
			foreach($rules as $field => $rule)
			{
				$label = ucwords(str_replace(array('_'), ' ', $field));
				
				$required_fields = array_merge(array($field), $required_fields);
				
				$this->EE->form_validation->set_rules($field, $label, $rule);
			}
			
			if ($this->EE->form_validation->run() == FALSE)
			{
				$error_count = 0;	
				
				foreach($required_fields as $field)
				{		
					$error = form_error($field);
							
					if($error !== FALSE && !empty($error))
					{	
						$this->set_field_error($field, $error);
					}
				}
			}
		}
		
		public function redirect()
		{
			$url = $this->return;
			
			if(isset($_POST['return']))
			{
				$url = $_POST['return'];
			}
				
			if(isset($_POST['secure_return']))
			{
				$this->secure_return = (int) $_POST['secure_return'] == 1 ? TRUE : FALSE;
			}
			
			if($this->secure_return === TRUE)
			{
				$url = str_replace('http://', 'https://', $url);
			}
			
			return $this->EE->functions->redirect($url);
		}
		
		public function current_url($uri_segments = TRUE)
		{
			$segments = $this->EE->uri->segment_array();
			
			$base_url = (!empty($_SERVER['HTTPS'])) ? 'https://'.$_SERVER['SERVER_NAME'] : 'http://'.$_SERVER['SERVER_NAME'];
			$uri	  = '';
			
			$port = $_SERVER['SERVER_PORT'] == "80" ? NULL : ':' . $_SERVER['SERVER_PORT'];
			
			if($uri_segments)
			{
				$uri = '/' . implode('/', $segments);
			}
			
			return $base_url . $port . $uri;
		}
		
		public function parse($vars, $tagdata = FALSE)
		{
			if($tagdata === FALSE)
			{
				$tagdata = $this->tagdata;
			}
				
			return $this->EE->TMPL->parse_variables($tagdata, $vars);
		}
		
		public function param($param, $default = FALSE, $boolean = FALSE, $required = FALSE)
		{
			$name	= $param;
			$param 	= $this->EE->TMPL->fetch_param($param);
			
			if($required && !$param) show_error('You must define a "'.$name.'" parameter in the '.__CLASS__.' tag.');
				
			if($param === FALSE && $default !== FALSE)
			{
				$param = $default;
			}
			else
			{				
				if($boolean)
				{
					$param = strtolower($param);
					$param = ($param == 'true' || $param == 'yes') ? TRUE : FALSE;
				}			
			}
			
			return $param;			
		}
			
	}
}