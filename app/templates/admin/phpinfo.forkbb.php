@extends ('layouts/admin')
      <section class="f-admin f-phpinfo">
        <h2>phpinfo()</h2>
        <div id="id-phpinfo-div">
          {!! $p->phpinfo !!}
        </div>
      </section>
