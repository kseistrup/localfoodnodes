@extends('admin.layout')

@section('title', 'Product production adjustment')

@section('content')
    @include('admin.page-header')

    @include('admin.product.shared.quick-links')

    @if ($product->productionType !== 'weekly')
        <div class="card">
            <div class="card-header">{{ trans('admin/product.adjust_production_quantity_per_week') }}</div>
            <div class="card-block">
                This feature is only available for weekly produced products.
            </div>
        </div>
    @else
        <form action="/account/producer/{{ $producer->id }}/product/{{ $product->id }}/production/adjustment/update" method="post">
            {{ csrf_field() }}
            <div class="row">
                <div class="col-12 col-xl-8">
                    <div class="row">
                        @foreach ($dates as $firstDateOfMonth => $dates)
                            <div class="col-12 col-xl-6 card-deck">
                                <div class="card">
                                    <div class="card-header">{{ date('Y F', strtotime($firstDateOfMonth)) }}</div>
                                    <div class="card-block">
                                        @foreach($dates as $date)
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <div style="width:25%">
                                                        <label>{{ date('d', strtotime('next monday', $date->getTimestamp())) }} -
                                                        {{ date('d', strtotime('next sunday', $date->getTimestamp())) }}</label>
                                                    </div>

                                                        <input type="number" class="form-control" name="quantity[{{ $date->format('Y') }}][{{ $date->format('W') }}]" id="{{ $date->format('W') }}" placeholder="{{ $product->getProductionQuantity() }}" value="{{ $product->productionAdjustmentQuantity($date->format('Y'), $date->format('W')) }}"><span class="input-group-addon">Products this week</span>

                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @component('admin.form-control-bar')
                <button type="submit" name="update" class="btn btn-success">{{ trans('admin/product.update_production') }}</button>
            @endcomponent
        </form>
    @endif
@endsection
