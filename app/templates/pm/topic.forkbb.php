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
@extends ('layouts/pm')
    <div class="f-pm f-nav-links">
      <div class="f-nlinks-b f-nlbpm">
    @yield ('pagination')
        <div class="f-actions-links">
          <small>{!! __('ACTIONS') !!}</small>
        @if ($p->model->closed)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-topic-closed" title="{{ __('Closed') }}"><span>{!! __('Closed') !!}</span></a></span>
        @endif
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-delete-dialog" title="{{ __('Delete dialogue') }}" href="{{ $p->model->linkDelete }}"><span>{!! __('Delete dialogue') !!}</span></a></span>
        @if ($p->model->canReply)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-post-reply" title="{{ __('Post reply') }}" href="{{ $p->model->linkReply }}"><span>{!! __('Post reply') !!}</span></a></span>
        @endif
        </div>
      </div>
    </div>
    <section id="fork-topic"  class="f-pm f-pmtopic">
      <h2>{{ $p->model->name }}</h2>
@foreach ($p->posts as $id => $post)
    @if (empty($post->id) && $iswev = [FORK_MESS_ERR => [['Message %s was not found in the database', $id]]])
        @include ('layouts/iswev')
    @else
      <article id="p{{ $post->id }}" class="f-post @if (FORK_GEN_MAN == $post->user->gender) f-user-male @elseif (FORK_GEN_FEM == $post->user->gender) f-user-female @endif @if ($post->user->online) f-user-online @endif @if (1 === $post->postNumber) f-post-first @endif">
        @if ($p->enableMod && $post->postNumber > 1)
        <input id="checkbox-{{ $post->id }}" class="f-post-checkbox" type="checkbox" name="ids[{{ $post->id }}]" value="{{ $post->id }}" form="id-form-mod">
        @endif
        <header class="f-post-header">
          <h3 class="f-phead-h3">@if ($post->postNumber > 1){!! __('Re') !!} @endif{{ $p->model->name }}</h3>
          <span class="f-post-posted"><time datetime="{{ \gmdate('c', $post->posted) }}">{{ dt($post->posted) }}</time></span>
        @if ($post->edited)
          <span class="f-post-edited" title="{{ __(['Last edit', $post->editor, dt($post->edited)]) }}">{!! __('Edited') !!}</span>
        @endif
          <span class="f-post-number"><a href="{{ $post->link }}" rel="bookmark">#{{ $post->postNumber }}</a></span>
        </header>
        <address class="f-post-user">
          <div class="f-post-usticky">
            <ul hidden class="f-user-info-first">
        @if ($p->user->viewUsers && $post->user->link)
              <li class="f-username"><a href="{{ $post->user->link }}" rel="author">{{ $post->user->username }}</a></li>
        @else
              <li class="f-username">{{ $post->user->username }}</li>
        @endif
            </ul>
        @if ($p->user->showAvatar && $post->user->avatar)
            <p class="f-avatar">
              <img alt="{{ $post->user->username }}" src="{{ $post->user->avatar }}" loading="lazy">
            </p>
        @endif
            <ul class="f-user-info">
        @if ($p->user->viewUsers && $post->user->link)
              <li class="f-username"><a href="{{ $post->user->link }}" rel="author">{{ $post->user->username }}</a></li>
        @else
              <li class="f-username">{{ $post->user->username }}</li>
        @endif
              <li class="f-usertitle">{{ $post->user->title() }}</li>
        @if (! $post->user->isGuest)
              <li class="f-userstatus">{!! __($post->user->online ? 'Online' : 'Offline') !!}</li>
        @endif
        @if ($p->user->showUserInfo && $p->user->showPostCount && $post->user->num_posts)
              <li class="f-postcount"><span class="f-psfont">{!! __(['%s post', $post->user->num_posts, num($post->user->num_posts)]) !!}</span></li>
        @endif
            </ul>
        @if ($p->user->showUserInfo)
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
          </div>
        @if ($p->user->showSignature && $post->user->isSignature)
          <aside class="f-post-sign">
            <div class="f-sign-brd">
              <small>- - -</small>
            </div>
            {!! $post->user->htmlSign !!}
          </aside>
        @endif
        @if ($post->canDelete || $post->canEdit || $post->canQuote || $post->canBlock)
          <aside class="f-post-btns">
            <small>{!! __('ACTIONS') !!}</small>
            @if ($post->canBlock)
            <small>-</small>
                @if (2 === $p->model->blockStatus)
            <a class="f-btn f-postunblock" title="{{ __('Unblock') }}" href="{{ $post->linkBlock }}"><span>{!! __('Unblock') !!}</span></a>
                @else
            <a class="f-btn f-postblock" title="{{ __('Block') }}" href="{{ $post->linkBlock }}"><span>{!! __('Block') !!}</span></a>
                @endif
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
    <div class="f-pm f-nav-links">
    @if ($p->form)
      <div class="f-nlinks">
    @else
      <div class="f-nlinks-a f-nlbpm">
    @endif
        <div class="f-actions-links">
          <small>{!! __('ACTIONS') !!}</small>
        @if ($p->model->canSend)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-send-dialog" title="{{ __(['Send dialogue to %s', $p->model->target]) }}" href="{{ $p->model->linkSend }}"><span>{!! __('Send dialogue') !!}</span></a></span>
        @endif
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-delete-dialog" title="{{ __('Delete dialogue') }}" href="{{ $p->model->linkDelete }}"><span>{!! __('Delete dialogue') !!}</span></a></span>
        @if ($p->model->canReply)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-post-reply" title="{{ __('Post reply') }}" href="{{ $p->model->linkReply }}"><span>{!! __('Post reply') !!}</span></a></span>
        @endif
        </div>
    @yield ('pagination')
      </div>
    </div>
@if ($form = $p->form)
    <section class="f-pm f-post-form">
      <h2>{!! __('Quick post') !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
