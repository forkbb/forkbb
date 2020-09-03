@include ('layouts/crumbs')
@section ('pagination')
    @if ($p->pagination)
        <nav class="f-pages">
        @foreach ($p->pagination as $cur)
            @if ($cur[2])
          <a class="f-page active" href="{!! $cur[0] !!}">{{ $cur[1] }}</a>
            @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! $cur[0] !!}</span>
            @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
            @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{!! $cur[0] !!}">{!! __('Previous') !!}</a>
            @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{!! $cur[0] !!}">{!! __('Next') !!}</a>
            @else
          <a class="f-page" href="{!! $cur[0] !!}">{{ $cur[1] }}</a>
            @endif
        @endforeach
        </nav>
    @endif
@endsection
@extends ('layouts/main')
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->pagination)
      <div class="f-nlinks-b">
    @yield ('pagination')
      </div>
@endif
    </div>
@if ($form = $p->form)
    <section class="f-main f-userlist-form">
      <h2>{!! __($p->user->searchUsers ? 'User search head' : 'User sort head') !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
@if ($p->userList)
    <section class="f-main f-userlist">
      <h2>{!! __('User_list') !!}</h2>
      <div class="f-ulist">
        <ol class="f-table">
          <li class="f-row f-thead" value="{{ $p->startNum }}">
            <span class="f-hcell f-cusername">
              <span class="f-hc-table">
                <span class="f-hc-tasc"><a @if (0 === $p->activeLink) class="active" @endif href="{!! $p->links[0] !!}">▲</a></span>
                <span class="f-hc-tname">{!! __('Username') !!}</span>
                <span class="f-hc-tdesc"><a @if (1 === $p->activeLink) class="active" @endif href="{!! $p->links[1] !!}">▼</a></span>
              </span>
            </span>
            <span class="f-hcell f-ctitle">
              <small>(</small>
              <span class="f-hc-tname">{!! __('Title') !!}</span>
              <small>),</small>
            </span>
    @if ($p->user->showPostCount)
            <span class="f-hcell f-cnumposts">
              <span class="f-hc-table">
                <span class="f-hc-tasc"><a @if (2 === $p->activeLink) class="active" @endif href="{!! $p->links[2] !!}">▲</a></span>
                <span class="f-hc-tname">{!! __('Posts') !!}</span>
                <span class="f-hc-tdesc"><a @if (3 === $p->activeLink) class="active" @endif href="{!! $p->links[3] !!}">▼</a></span>
              </span>
              <small>,</small>
            </span>
    @endif
            <span class="f-hcell f-cdatereg">
              <span class="f-hc-table">
                <span class="f-hc-tasc"><a @if (4 === $p->activeLink) class="active" @endif href="{!! $p->links[4] !!}">▲</a></span>
                <span class="f-hc-tname">{!! __('Registered') !!}</span>
                <span class="f-hc-tdesc"><a @if (5 === $p->activeLink) class="active" @endif href="{!! $p->links[5] !!}">▼</a></span>
              </span>
            </span>
          </li>
    @foreach ($p->userList as $user)
          <li class="f-row">
        @if ($p->user->viewUsers && $user->link)
            <span class="f-cell f-cusername"><a href="{!! $user->link !!}">{{ $user->username }}</a></span>
        @else
            <span class="f-cell f-cusername">{{ $user->username }}</span>
        @endif
            <span class="f-cell f-ctitle"><small>(</small><i>{{ $user->title() }}</i><small>),</small></span>
        @if ($p->user->showPostCount)
            <span class="f-cell f-cnumposts">{!! __('<b>%s</b><small> post,</small>', $user->num_posts, num($user->num_posts)) !!}</span>
        @endif
            <span class="f-cell f-cdatereg">{!! __('<small>registered: </small><b>%s</b>', dt($user->registered, true)) !!}</span>
          </li>
    @endforeach
        </ol>
      </div>
    </section>
    @if ($p->pagination)
    <div class="f-nav-links">
      <div class="f-nlinks">
        @yield ('pagination')
      </div>
    </div>
    @endif
@endif
