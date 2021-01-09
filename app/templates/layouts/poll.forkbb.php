        <div class="f-post-poll">
@if ($poll->canVote)
          <form class="f-form" method="post" action="{!! $poll->link !!}">
            <input type="hidden" name="token" value="{{ $poll->token }}">
@endif
@foreach ($poll->question as $q => $question)
            <fieldset id="id-question-{!! $q !!}" class="f-poll-q">
              <legend class="f-poll-ql">{!! __('Question %s legend', $q) !!}</legend>
              <h3 class="f-poll-qt">{{ $question }}</h3>
    @if (($poll->canVote || ! $poll->tid) && $poll->type[$q] > 1)
              <p class="f-poll-mult">{!! __('You can choose up to %s answers', $poll->type[$q]) !!}</p>
    @endif
              <ol class="f-poll-as">
    @foreach ($poll->answer[$q] as $a => $answer)
                <li id="id-answer-{!! $q . '-' . $a !!}" class="f-poll-a">
        @if ($poll->canVote || ! $poll->tid)
                 <label class="f-poll-al">
            @if ($poll->type[$q] > 1)
                    <input class="f-poll-ai" type="checkbox" name="poll_vote[{!! $q !!}][{!! $a !!}]" value="1" />
            @else
                    <input class="f-poll-ai" type="radio" name="poll_vote[{!! $q !!}][0]" value="{!! $a !!}" />
            @endif
                    <span class="f-poll-at">{{ $answer }}</span>
                  </label>
        @elseif ($poll->canSeeResult)
                  <span class="f-poll-at">{{ $answer }}</span>
                  <span class="f-poll-ap">{!! __('(%1$s [%2$s%%])', $poll->vote[$q][$a], $poll->percVote[$q][$a]) !!}</span>
                  <p class="f-poll-ab"><span class="f-poll-ab-s1 width{{ $poll->widthVote[$q][$a] }}"><span class="f-poll-ab-s2">{{ $poll->widthVote[$q][$a] }}%</span></span></p>
        @else
                  <label class="f-poll-al">
                    <span class="f-poll-at">{{ $answer }}</span>
                  </label>
        @endif
                </li>
    @endforeach
              </ol>
              <p class="f-poll-total">{!! __('In total voted: %s', $poll->total[$q] ?? 0) !!}</p>
            </fieldset>
@endforeach
@if ($poll->canVote)
            <p class="f-poll-btns">
              <button class="f-btn" name="vote" value="{!! __('Vote') !!}" title="{!! __('Vote') !!}"><span>{!! __('Vote') !!}</span></button>
            </p>
          </form>
@else
    @if (null !== $poll->status)
          <p class="f-poll-status"><span>{!! $poll->status !!}</span></p>
    @endif
@endif
        </div>
