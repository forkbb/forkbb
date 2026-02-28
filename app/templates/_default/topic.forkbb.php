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
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{{ $p->model->name }}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE linksBBefore -->
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
          <span class="f-act-span"><a class="f-btn f-btn-post-reply" title="{{ __('Post reply') }}" href="{{ $p->model->linkReply }}" rel="nofollow"><span>{!! __('Post reply') !!}</span></a></span>
        @endif
        </div>
    @endif
      </div>
@endif
    </div>
    <!-- PRE linksBAfter -->
@if ($p->model->toc)
    <!-- PRE tocBefore -->
    <section id="fork-toc" class="f-main">
      <h2>{!! __('Table of content') !!}</h2>
      <details class="f-toc-det">
        <summary class="f-toc-sum">{!! __('Table of content') !!}</summary>
        <div class="f-toc-div"><!-- inline -->
    @php $level = 0; @endphp
    @foreach ($p->model->tableOfContent as $cur)
        @if ($cur['level'] > $level)
            @while ($cur['level'] > $level)
          <ul class="f-toc-ul"><li class="f-toc-li">
              @php ++$level; @endphp
            @endwhile
        @elseif ($cur['level'] < $level)
            @while ($cur['level'] < $level)
          </li></ul>
              @php --$level; @endphp
            @endwhile
          </li><li class="f-toc-li">
        @else
          </li><li class="f-toc-li">
        @endif
          <a  class="f-toc-a" href="{{ $cur['url'] }}">{{ $cur['value'] }}</a>
    @endforeach
    @while ($level > 0)
          </li></ul>
        @php --$level; @endphp
    @endwhile
        </div><!-- endinline -->
      </details>
    </section>
    <!-- PRE tocAfter -->
@endif
    <!-- PRE mainBefore -->
    <section id="fork-topic" class="f-main">
      <h2>{!! __('Post list') !!}</h2>
@foreach ($p->posts as $id => $post)
    @empty ($post->id)
        @php $iswev = [FORK_MESS_ERR => [['Message %s was not found in the database', $id]]]; @endphp
        @include ('layouts/iswev')
    @else
      <article id="p{!! (int) $post->id !!}" class="f-post @if (FORK_GEN_MAN == $post->user->gender) f-user-male @elseif (FORK_GEN_FEM == $post->user->gender) f-user-female @endif @if ($post->user->online) f-user-online @endif @if (1 === $post->postNumber) f-post-first @endif @if ($post->id === $p->model->solution) f-post-solution @endif">
        @if ($p->enableMod && $post->postNumber > 1)
        <input hidden id="checkbox-{!! (int) $post->id !!}" class="f-post-checkbox" type="checkbox" name="ids[{!! (int) $post->id !!}]" value="{!! (int) $post->id !!}" form="id-form-mod">
        @endif
        <header class="f-post-header">
          <h3 class="f-phead-h3">@if ($post->postNumber > 1){!! __('Re') !!} @endif{{ $p->model->name }}</h3>
        @if ($p->enableMod && $post->postNumber > 1)
          <label class="f-post-posted" for="checkbox-{!! (int) $post->id !!}" title="{{ __('Select for moderation') }}"><time datetime="{{ \gmdate('c', $post->posted) }}">{{ dt($post->posted) }}</time></label>
        @else
          <span class="f-post-posted"><time datetime="{{ \gmdate('c', $post->posted) }}">{{ dt($post->posted) }}</time></span>
        @endif
        @if ($post->canEdit && $p->user->isAdmin)
          <span class="f-post-change" title="{{ __('Change author and date') }}"><a class="f-post-change-a" href="{{ $post->linkAnD }}"><span>&#9881;</span></a></span>
        @endif
        @if ($post->edited)
          <span class="f-post-edited" title="{{! __(['Last edit', $post->editor, dt($post->edited)]) !}}"><span>{!! __('Edited') !!}</span></span>
        @endif
        @if ($post->id === $p->model->solution)
          <span class="f-post-solution-info" title="{{! __(['This is solution. %1$s (%2$s)', $p->model->solution_wa, dt($p->model->solution_time)]) !}}"><span>{!! __('Solution') !!}</span></span>
        @endif
        @if ($p->user->isGuest)
          <span class="f-post-number"><a href="#p{!! (int) $post->id !!}" rel="bookmark">#{!! (int) $post->postNumber !!}</a></span>
        @else
          <span class="f-post-number"><a href="{{ $post->link }}" rel="bookmark">#{!! (int) $post->postNumber !!}</a></span>
        @endif
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
            <input id="id-uibx-{!! (int) $post->id !!}" class="f-user-info-checkbox" type="checkbox" hidden>
            <label class="f-user-info-toggle" for="id-uibx-{!! (int) $post->id !!}" title="{!! __('Info') !!}" hidden><span class="f-user-info-tsp" hidden>{!! __('Info') !!}</span></label>
            <ul class="f-user-info-add">
            @if ($p->user->isAdmMod && '' != $post->user->admin_note)
              <li class="f-admin-note" title="{{ __('Admin note') }}">{{ $post->user->admin_note }}</li>
            @endif
            @if (! $post->user->isGuest )
              <li class="f-registered"><span class="f-psfont">{!! __(['Registered: %s', dt($post->user->registered, null, 0)]) !!}</span></li>
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
            @for ($snI = 1; $snI < 6; $snI++)
                @php $snF = "sn_profile{$snI}"; @endphp
                @if ($post->user->$snF)
                    @php list($snK, $snT, $snV) = \explode("\n", $post->user->$snF, 3); @endphp
              <li class="f-user-contacts f-social-link f-sn-{{ $snK}}"><a href="{{ $snV }}" title="{{ $snT }}" rel="ugc"><span class="f-psfont"><span>{{ $snT }}</span></span></a></li>
                @endif
            @endfor
            @if ($post->user->linkEmail)
              <li class="f-user-contacts f-email"><a href="{{ $post->user->linkEmail }}" title="{{ __('Email title') }}" rel="ugc"><span class="f-psfont">{!! __('Email ') !!}</span></a></li>
            @endif
            </ul>
        @endif
          </div>
        </address>
        <div class="f-post-body">
          <div class="f-post-main">
        @if (1 === $post->postNumber && $p->model->customFieldsCurLevel > 0)
            <div class="f-post-customfields">
            @foreach ($p->model->cf_data as $field)
                @if ($p->model->customFieldsCurLevel >= $field['visibility'])
              <p class="f-post-customfield"><span class="f-post-cf-name">{!! __($field['name']) !!}</span> : <span class="f-post-cf-value">{{ $field['value'] }}</span></p>
                @endif
            @endforeach
            </div>
        @endif
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
        @php $showPostReaction = $p->userRules->showReaction && (! empty($post->reactions) || $post->useReaction) && ! empty($reactions = $post->reactionData()); @endphp
        @php $showPostBtns = $post->canReport || $post->canDelete || $post->canEdit || $post->canQuote || $p->model->canChSolution || (1 === $post->postNumber && $p->model->solution > 0); @endphp
        @if ($showPostReaction || $showPostBtns)
          <aside class="f-post-bfooter">
            @if ($showPostReaction)
            <div class="f-post-reaction">
                @include ('layouts/reaction')
            </div>
            @endif
            @if ($showPostBtns)
            <div class="f-post-btns">
              <small>{!! __('ACTIONS') !!}</small>
                @if (1 === $post->postNumber && $p->model->solution > 0)
              <small>-</small>
              <a class="f-btn f-gotosolution" title="{{ __('Go to solution') }}" href="{{ $p->model->linkGoToSolution }}" rel="nofollow"><span>{!! __('Go to solution') !!}</span></a>
                @endif
                @if ($p->model->canChSolution)
              <small>-</small>
              <a class="f-btn f-postsolution" title="{{ __('Solution') }}" href="{{ $post->linkSolution }}" rel="nofollow"><span>{!! __('Solution') !!}</span></a>
                @endif
                @if ($post->canReport)
              <small>-</small>
              <a class="f-btn f-minor f-postreport" title="{{ __('Report') }}" href="{{ $post->linkReport }}" rel="nofollow"><span>{!! __('Report') !!}</span></a>
                @endif
                @if ($post->canDelete)
              <small>-</small>
              <a class="f-btn f-postdelete" title="{{ __('Delete') }}" href="{{ $post->linkDelete }}" rel="nofollow"><span>{!! __('Delete') !!}</span></a>
                @endif
                @if ($post->canEdit)
              <small>-</small>
              <a class="f-btn f-postedit" title="{{ __('Edit') }}" href="{{ $post->linkEdit }}" rel="nofollow"><span>{!! __('Edit') !!}</span></a>
                @endif
                @if ($post->canQuote)
              <small>-</small>
              <a class="f-btn f-postquote" title="{{ __('Quote') }}" href="{{ $post->linkQuote }}" rel="nofollow"><span>{!! __('Quote') !!}</span></a>
                @endif
            </div>
            @endif
          </aside>
        @endif
        </div>
      </article>
    @endempty
@endforeach
    </section>
    <!-- PRE mainAfter -->
    <!-- PRE linksABefore -->
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
          <span class="f-act-span"><a class="f-btn f-btn-post-reply" title="{{ __('Post reply') }}" href="{{ $p->model->linkReply }}" rel="nofollow"><span>{!! __('Post reply') !!}</span></a></span>
        @endif
        </div>
    @endif
    @yield ('pagination')
      </div>
@endif
@yield ('crumbs')
    </div>
    <!-- PRE linksAAfter -->
@if ($p->enableMod && $form = $p->formMod)
    <!-- PRE modBefore -->
    <aside id="fork-mod" class="f-moderate">
      <h2>{!! __('Moderate') !!}</h2>
      <div class="f-fdivm">
    @include ('layouts/form')
      </div>
    </aside>
    <!-- PRE modAfter -->
@endif
@if ($p->online)
    <!-- PRE statsBefore -->
    @include ('layouts/stats')
    <!-- PRE statsAfter -->
@endif
@if ($form = $p->form)
    <!-- PRE quickBefore -->
    <section class="f-post-form">
      <h2>{!! __('Quick post') !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
    <!-- PRE quickAfter -->
@endif
    <!-- PRE end -->
