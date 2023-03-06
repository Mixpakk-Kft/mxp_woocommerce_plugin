<?php

class Mixpakk_Exception extends RuntimeException
{
    protected bool $change_status = false;
    protected bool $filter_error = false;

    public function __construct(string $message = "", bool $change_status = false, bool $filter_error = false)
    {
        parent::__construct($message);
        $this->change_status = $change_status;
        $this->filter_error = $filter_error;
    }

    public function doChangeStatus() : bool
    {
        return $this->change_status;
    }

    public function isFilterError() : bool
    {
        return $this->filter_error;
    }
};