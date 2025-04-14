<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/product-categories', function () {
    return view('products.categories');
});

Route::get('/product-subcategories', function () {
    return view('products.subcategories');
});

Route::get('/product-attributes', function () {
    return view('products.attributes');
});

Route::get('/products', function () {
    return view('products.index');
});

Route::get('/manufacturing', function () {
    return view('manufacturing.index');
});

Route::get('/manufacturing/create', function () {
    return view('manufacturing.create');
});
