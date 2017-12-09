@extends ('layouts/main')
    <section class="f-main f-phpinfo">
      <h2>phpinfo()</h2>
      <div class="f-phpinfo-div">
        {!! $p->phpinfo !!}
      </div>
    </section>
