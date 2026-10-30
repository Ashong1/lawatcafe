@extends('errors.layout')
@section('code', $exception->getStatusCode())
@section('heading', "That didn't work")
@section('message', 'Go back and try again.')
