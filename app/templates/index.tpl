@extends ('layouts/main')
@if ($p->categoryes)
    <section class="f-main">
      <ol class="f-ftlist">
  @foreach ($p->categoryes as $id => $forums)
        <li id="cat-{!! $id !!}" class="f-category">
          <h2>{{ current($forums)->cat_name }}</h2>
          <ol class="f-table">
            <li class="f-row f-thead" value="0">
              <div class="f-hcell f-cmain">{!! __('Forum') !!}</div>
              <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
              <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
            </li>
    @include ('layouts/subforums')
          </ol>
        </li>
  @endforeach
      </ol>
    </section>
@endif
@include ('layouts/stats')
