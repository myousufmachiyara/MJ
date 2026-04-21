@extends('layouts.app')
@section('title', 'New Consignment')

@section('content')
@php $isEdit = false; $itemsJson = '[]'; @endphp
@include('consignments.form')
@endsection