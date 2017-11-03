    <section class="f-debug">
      <h2>{!! __('Debug table') !!}</h2>
      <p class="f-debugtime">[ {!! __('Querytime', $p->time, $p->numQueries) !!} - {!! __('Memory usage', $p->memory) !!} {!! __('Peak usage', $p->peak) !!} ]</p>
@if($p->queries)
      <table>
        <thead>
          <tr>
            <th class="tcl" scope="col">{!! __('Query times') !!}</th>
            <th class="tcr" scope="col">{!! __('Query') !!}</th>
          </tr>
        </thead>
        <tbody>
@foreach($p->queries as $cur)
          <tr>
            <td class="tcl">{{ $cur[1] }}</td>
            <td class="tcr">{{ $cur[0] }}</td>
          </tr>
@endforeach
          <tr>
            <td class="tcl">{{ $p->total }}</td>
            <td class="tcr"></td>
          </tr>
        </tbody>
      </table>
@endif
    </section>
