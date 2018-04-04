@section ('crumbs')
      <ul class="f-crumbs">
  @foreach ($p->crumbs as $cur)
        <li class="f-crumb"><!-- inline -->
    @if ($cur[0])
          <a href="{!! $cur[0] !!}" @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</a>
    @else
          <span @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</span>
    @endif
        </li><!-- endinline -->
  @endforeach
      </ul>
@endsection
@section ('linkpost')
  @if ($p->model->canReply || $p->model->closed)
        <div class="f-actions-links">
    @if ($p->model->closed)
          {!! __('Topic closed') !!}
    @else
          <a class="f-btn f-btn-post-reply" href="{!! $p->model->linkReply !!}">{!! __('Post reply') !!}</a>
    @endif
        </div>
  @endif
@endsection
@section ('pagination')
  @if ($p->model->pagination)
        <nav class="f-pages">
    @foreach ($p->model->pagination as $cur)
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
@if ($p->model->canReply || $p->model->closed || $p->model->pagination)
      <div class="f-nlinks-b">
  @yield ('pagination')
  @yield ('linkpost')
      </div>
@endif
    </div>
    <section class="f-main f-topic">
      <h2>{{ cens($p->model->subject) }}</h2>
@foreach ($p->posts as $id => $post)
  @if (empty($post->id) && $iswev = ['e' => [__('Message %s was not found in the database', $id)]])
    @include ('layouts/iswev')
  @else
      <article id="p{!! $post->id !!}" class="f-post @if ($post->user->gender == 1) f-user-male @elseif ($post->user->gender == 2) f-user-female @endif @if ($post->user->online) f-user-online @endif @if (1 === $post->postNumber) f-post-first @endif">
        <header class="f-post-header clearfix">
          <h3>@if ($post->postNumber > 1) {!! __('Re') !!} @endif {{ cens($p->model->subject) }}</h3>
          <span class="f-post-posted"><a href="{!! $post->link !!}" rel="bookmark"><time datetime="{{ utc($post->posted) }}">{{ dt($post->posted) }}</time></a></span>
    @if ($post->edited)
          <span class="f-post-edited" title="{!! __('Last edit', $post->edited_by, dt($post->edited)) !!}">{!! __('Edited') !!}</span>
    @endif
          <span class="f-post-number">#{!! $post->postNumber !!}</span>
        </header>
        <div class="f-post-body clearfix">
          <address class="f-post-left">
            <ul class="f-user-info">
    @if ($p->user->viewUsers && $post->user->link)
              <li class="f-username"><a href="{!! $post->user->link !!}">{{ $post->user->username }}</a></li>
    @else
              <li class="f-username">{{ $post->user->username }}</li>
    @endif
    @if ($p->user->showAvatar && $post->user->avatar)
              <li class="f-avatar">
                <img alt="{{ $post->user->username }}" src="{!! $post->user->avatar !!}">
              </li>
    @endif
              <li class="f-usertitle">{{ $post->user->title() }}</li>
    @if ($p->user->showUserInfo && $p->user->showPostCount && $post->user->num_posts)
              <li class="f-postcount">{!! __('%s post', $post->user->num_posts, num($post->user->num_posts)) !!}</li>
    @endif
            </ul>
    @if ($p->user->showUserInfo)
            <ul class="f-user-info-add">
              <li>{!! __('Registered:') !!} {{ dt($post->user->registered, true) }}</li>
      @if ($post->user->location)
              <li>{!! __('From') !!} {{ cens($post->user->location) }}</li>
      @endif
            </ul>
    @endif
          </address>
          <div class="f-post-right f-post-main">
            {!! $post->html() !!}
          </div>
    @if ($p->user->showSignature && '' != $post->user->signature)
          <div class="f-post-right f-post-signature">
            <hr>
            {!! $post->user->htmlSign !!}
          </div>
    @endif
        </div>
        <footer class="f-post-footer clearfix">
          <div class="f-post-left">
            <span class="f-userstatus">{!! __($post->user->online ? 'Online' : 'Offline') !!}</span>
          </div>
    @if ($post->canReport || $post->canDelete || $post->canEdit || $post->canQuote)
          <div class="f-post-right">
            <ul>
      @if ($post->canReport)
              <li class="f-postreport"><a class="f-btn f-minor" href="{!! $post->linkReport !!}">{!! __('Report') !!}</a></li>
      @endif
      @if ($post->canDelete)
              <li class="f-postdelete"><a class="f-btn" href="{!! $post->linkDelete !!}">{!! __('Delete') !!}</a></li>
      @endif
      @if ($post->canEdit)
              <li class="f-postedit"><a class="f-btn" href="{!! $post->linkEdit !!}">{!! __('Edit') !!}</a></li>
      @endif
      @if ($post->canQuote)
              <li class="f-postquote"><a class="f-btn" href="{!! $post->linkQuote !!}">{!! __('Quote') !!}</a></li>
      @endif
            </ul>
          </div>
    @endif
        </footer>
      </article>
  @endif
@endforeach
    </section>
    <div class="f-nav-links">
@if ($p->model->canReply || $p->model->closed || $p->model->pagination)
      <div class="f-nlinks-a">
  @yield ('linkpost')
  @yield ('pagination')
      </div>
@endif
@yield ('crumbs')
    </div>
@if ($p->online)
  @include ('layouts/stats')
@endif
@if ($form = $p->form)
    <section class="f-post-form f-btnsrow-form">
      <h2>{!! __('Quick post') !!}</h2>
      <div class="f-fdiv">
  @include ('layouts/form')
      </div>
    </section>
@endif
