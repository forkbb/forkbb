@extends ('layouts/main')
@if ($p->categoryes)
    <section class="f-main">
      <ol class="f-ftlist">
    @foreach ($p->categoryes as $id => $forums)
        <li id="cat-{!! $id !!}" class="f-category">
          <h2 class="f-ftch2">{{ current($forums)->cat_name }}</h2>
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
    @if ($p->linkMarkRead)
    <div class="f-nav-links">
      <div class="f-nlinks">
        <div class="f-actions-links">
          <a class="f-btn f-btn-markread" title="{!! __('Mark all as read') !!}" href="{!! $p->linkMarkRead !!}">{!! __('All is read') !!}</a>
        </div>
      </div>
    </div>
    @endif
@endif
@include ('layouts/stats')
