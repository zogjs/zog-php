<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@{{ $page_title }}</title>
</head>
<body>

<div>
    <h1>@{{ $page_title }}</h1>

    <div zp-for="$product, $key of $products">
        <div>[Index:@{{$key+1}}]  ID: @{{ $product['id'] }}</div>

        <div>Name : @{{ $product['name'] }}  Model :  <strong>@{{ $product['model'] }}</strong></div>
        <div>
            <div zp-if="$product['amount'] > 0">Amount : @{{$product['amount']}}</div>
            <div zp-else>Free</div>
        </div>
        <hr>
    </div>
</div>

@php( $date = date('Y-m-d'); )
<div> @{{$date}} </div>
</body>
</html>