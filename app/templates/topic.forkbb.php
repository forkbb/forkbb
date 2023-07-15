@include ('layouts/crumbs')
@section ('pagination')
    @if ($p->model->pagination)
        <nav class="f-pages">
        @foreach ($p->model->pagination as $cur)
            @if ($cur[2])
          <a class="f-page active" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! __($cur[0]) !!}</span>
            @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
            @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{{ $cur[0] }}" title="{{ __('Previous') }}"><span>{!! __('Previous') !!}</span></a>
            @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{{ $cur[0] }}" title="{{ __('Next') }}"><span>{!! __('Next') !!}</span></a>
            @else
          <a class="f-page" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @endif
        @endforeach
        </nav>
    @endif
@endsection
@extends ('layouts/main')
    <div class="f-mheader">
      <h1 id="fork-h1">{{ $p->model->name }}</h1>
    </div>
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->model->canReply || $p->model->closed || $p->model->pagination)
      <div class="f-nlinks-b">
    @yield ('pagination')
    @if ($p->model->canReply || $p->model->closed)
        <div class="f-actions-links">
          <small>{!! __('ACTIONS') !!}</small>
        @if ($p->model->closed)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-topic-closed" title="{{ __('Topic closed') }}"><span>{!! __('Topic closed') !!}</span></a></span>
        @endif
        @if ($p->model->canReply)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-post-reply" title="{{ __('Post reply') }}" href="{{ $p->model->linkReply }}"><span>{!! __('Post reply') !!}</span></a></span>
        @endif
        </div>
    @endif
      </div>
@endif
    </div>
    <section id="fork-topic" class="f-main">
      <h2>{!! __('Post list') !!}</h2>
@foreach ($p->posts as $id => $post)
    @if (empty($post->id) && $iswev = [FORK_MESS_ERR => [['Message %s was not found in the database', $id]]])
        @include ('layouts/iswev')
    @else
      <article id="p{{ $post->id }}" class="f-post @if (FORK_GEN_MAN == $post->user->gender) f-user-male @elseif (FORK_GEN_FEM == $post->user->gender) f-user-female @endif @if ($post->user->online) f-user-online @endif @if (1 === $post->postNumber) f-post-first @endif">
        @if ($p->enableMod && $post->postNumber > 1)
        <input hidden id="checkbox-{{ $post->id }}" class="f-post-checkbox" type="checkbox" name="ids[{{ $post->id }}]" value="{{ $post->id }}" form="id-form-mod">
        @endif
        <header class="f-post-header">
          <h3 class="f-phead-h3">@if ($post->postNumber > 1){!! __('Re') !!} @endif{{ $p->model->name }}</h3>
        @if ($p->enableMod && $post->postNumber > 1)
          <label class="f-post-posted" for="checkbox-{{ $post->id }}" title="{{ __('Select for moderation') }}"><time datetime="{{ \gmdate('c', $post->posted) }}">{{ dt($post->posted) }}</time></label>
        @else
          <span class="f-post-posted"><time datetime="{{ \gmdate('c', $post->posted) }}">{{ dt($post->posted) }}</time></span>
        @endif
        @if ($post->edited)
          <span class="f-post-edited" title="{{ __(['Last edit', $post->editor, dt($post->edited)]) }}"><span>{!! __('Edited') !!}</span></span>
        @endif
          <span class="f-post-number"><a href="{{ $post->link }}" rel="bookmark">#{{ $post->postNumber }}</a></span>
        </header>
        <address class="f-post-user">
          <div class="f-post-usticky">
            <ul hidden class="f-user-info-first">
        @if ($p->userRules->viewUsers && $post->user->link)
              <li class="f-username"><a href="{{ $post->user->link }}" rel="author">{{ $post->user->username }}</a></li>
        @else
              <li class="f-username">{{ $post->user->username }}</li>
        @endif
            </ul>
        @if ($p->userRules->showAvatar && $post->user->avatar)
            <p class="f-avatar">
              <img alt="{{ $post->user->username }}" src="{{ $post->user->avatar }}" loading="lazy">
            </p>
        @endif
            <ul class="f-user-info">
        @if ($p->userRules->viewUsers && $post->user->link)
              <li class="f-username"><a href="{{ $post->user->link }}" rel="author">{{ $post->user->username }}</a></li>
        @else
              <li class="f-username">{{ $post->user->username }}</li>
        @endif
              <li class="f-usertitle">{{ $post->user->title() }}</li>
        @if (! $post->user->isGuest)
              <li class="f-userstatus">{!! __($post->user->online ? 'Online' : 'Offline') !!}</li>
        @endif
        @if ($p->userRules->showUserInfo && $p->userRules->showPostCount && $post->user->num_posts)
              <li class="f-postcount"><span class="f-psfont">{!! __(['%s post', $post->user->num_posts, num($post->user->num_posts)]) !!}</span></li>
        @endif
        @if ($linkPromote = $p->user->linkPromote($post))
              <li class="f-promoteuser"><a href="{{ $linkPromote }}" title="{{ __('Promote user title') }}"><span class="f-psfont">{!! __('Promote user') !!}</span></a></li>
        @endif
            </ul>
        @if ($p->userRules->showUserInfo)
            <ul class="f-user-info-add">
            @if ($p->user->isAdmMod && '' != $post->user->admin_note)
              <li class="f-admin-note" title="{{ __('Admin note') }}">{{ $post->user->admin_note }}</li>
            @endif
            @if (! $post->user->isGuest )
              <li class="f-registered"><span class="f-psfont">{!! __(['Registered: %s', dt($post->user->registered, true)]) !!}</span></li>
            @endif
            @if ($post->user->location)
              <li class="f-location"><span class="f-psfont">{!! __(['From %s', $post->user->censorLocation]) !!}</span></li>
            @endif
            @if ($p->userRules->viewIP)
              <li class="f-poster-ip"><a href="{{ $post->linkGetHost }}" title="{{ $post->poster_ip }}"><span class="f-psfont">{!! __('IP address logged') !!}</span></a></li>
            @endif
            @if ($post->user->url)
              <li class="f-user-contacts f-website"><a href="{{ $post->user->censorUrl }}" title="{{ __('Website title') }}" rel="ugc"><span class="f-psfont">{!! __('Website') !!}</span></a></li>
            @endif
            @if (($post->user->isGuest && $post->user->email && $p->user->isAdmMod) || 0 === $post->user->email_setting)
              <li class="f-user-contacts f-email"><a href="mailto:{{ $post->user->censorEmail }}" title="{{ __('Email title') }}" rel="ugc"><span class="f-psfont">{!! __('Email ') !!}</span></a></li>
            @endif
            </ul>
        @endif
          </div>
        </address>
        <div class="f-post-body">
          <div class="f-post-main">
            {!! $post->html() !!}
        @if (1 === $post->postNumber && ($poll = $p->poll))
            @include ('layouts/poll')
        @endif
          </div>
        @if ($p->userRules->showSignature && $post->user->isSignature)
          <aside class="f-post-sign">
            <div class="f-sign-brd">
              <small>- - -</small>
            </div>
            {!! $post->user->htmlSign !!}
          </aside>
        @endif
        @if ($post->canReport || $post->canDelete || $post->canEdit || $post->canQuote)
          <aside class="f-post-btns">
            <small>{!! __('ACTIONS') !!}</small>
            @if ($post->canReport)
            <small>-</small>
            <a class="f-btn f-minor f-postreport" title="{{ __('Report') }}" href="{{ $post->linkReport }}"><span>{!! __('Report') !!}</span></a>
            @endif
            @if ($post->canDelete)
            <small>-</small>
            <a class="f-btn f-postdelete" title="{{ __('Delete') }}" href="{{ $post->linkDelete }}"><span>{!! __('Delete') !!}</span></a>
            @endif
            @if ($post->canEdit)
            <small>-</small>
            <a class="f-btn f-postedit" title="{{ __('Edit') }}" href="{{ $post->linkEdit }}"><span>{!! __('Edit') !!}</span></a>
            @endif
            @if ($post->canQuote)
            <small>-</small>
            <a class="f-btn f-postquote" title="{{ __('Quote') }}" href="{{ $post->linkQuote }}"><span>{!! __('Quote') !!}</span></a>
            @endif
          </aside>
        @endif
        </div>
      </article>
    @endif
@endforeach
    </section>
    <div class="f-nav-links">
@if ($p->model->canReply || $p->model->pagination || $p->model->canSubscription)
      <div class="f-nlinks-a">
    @if ($p->model->canReply || $p->model->canSubscription)
        <div class="f-actions-links">
          <small>{!! __('ACTIONS') !!}</small>
        @if ($p->model->canSubscription)
          <small>|</small>
            @if ($p->model->is_subscribed)
          <span class="f-act-span"><a class="f-btn f-btn-unsubscribe f-opacity" title="{{ __('Unsubscribe topic') }}" href="{{ $p->model->linkUnsubscribe }}"><span>{!! __('Unsubscribe') !!}</span></a></span>
            @else
          <span class="f-act-span"><a class="f-btn f-btn-subscribe f-opacity" title="{{ __('Subscribe topic') }}" href="{{ $p->model->linkSubscribe }}"><span>{!! __('Subscribe') !!}</span></a></span>
            @endif
        @endif
        @if ($p->model->canReply)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-post-reply" title="{{ __('Post reply') }}" href="{{ $p->model->linkReply }}"><span>{!! __('Post reply') !!}</span></a></span>
        @endif
        </div>
    @endif
    @yield ('pagination')
      </div>
@endif
@yield ('crumbs')
    </div>
@if ($p->enableMod && $form = $p->formMod)
    <aside id="fork-mod" class="f-moderate">
      <h2>{!! __('Moderate') !!}</h2>
      <div class="f-fdivm">
    @include ('layouts/form')
      </div>
    </aside>
@endif
@if ($p->online)
    @include ('layouts/stats')
@endif
@if ($form = $p->form)
    <section class="f-post-form">
      <h2>{!! __('Quick post') !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
