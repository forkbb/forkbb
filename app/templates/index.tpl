@extends('layouts/main')
@if($forums)
    <section class="f-main">
      <ol class="f-ftlist">
@foreach($forums as $id => $cat)
        <li id="cat-{!! $id !!}" class="f-category">
          <h2>{{ $cat['name'] }}</h2>
          <ol class="f-table">
            <li class="f-row f-thead" value="0">
              <div class="f-hcell f-cmain">{!! __('Forum') !!}</div>
              <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
              <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
            </li>
@include('layouts/subforums')
          </ol>
        </li>
@endforeach
      </ol>
    </section>
@else
    <section class="f-main f-message">
      <h2>{!! __('Empty board') !!}</h2>
    </section>
@endif
@include('layouts/stats')
