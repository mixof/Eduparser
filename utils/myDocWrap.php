<?php

require_once __DIR__."/phpQuery.php";

class MyDocWrap implements arrayaccess {
    
	private $doc;
	private $data;
    public function __construct($data) 
	{
		$this->data=$data;
		//phpQuery::unloadDocuments();
        $this->doc=phpQuery::newDocumentHTML($data);
    }

  	public function __destruct() 
	{
		//phpQuery::unloadDocuments();
        unset($this->doc);
		unset($this->data);
    }

	public function clear()
	{
		if($this->doc) $this->doc->unloadDocument();
		$this->doc=false;
	}
	
	public function isLoaded()
	{
		if(!$this->doc) return false;
		return true;
	}

	public function getUrl()
	{
		if($this->doc && $this->doc->document->baseURI)  return $this->doc->document->baseURI;
		return false;
	}

	public function getHtml()
	{
		return $this->data;
	}

	public function getText()
	{
		if($this->doc) return $this->doc->document->textContent;
		return false;
	}

	public function getDoc()
	{
		return $this->doc;
	}

	public function unloadDocument()
	{
		if($this->doc) $this->doc->unloadDocument();
	}


    public function offsetExists($offset) 
	{
		if($this->doc) return ($this->doc[$offset]->length > 0);
		return false;
    }

	//if element is found, then return it
	//otherwise return empty element
    public function offsetGet($offset) 
	{
        if($this->doc && $this->doc[$offset]->length > 0) return $this->doc[$offset];
		else return phpQuery::newDocument();
    }

	 public function offsetUnset (  $offset )
	{
		
	}
	 public function offsetSet (  $offset ,  $value )
	{
		
	}
	
	
}


?>
