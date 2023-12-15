@php $post = $p->post @endphp
@php $reactions = $post->reactionData() @endphp
@include ('layouts/reaction')
