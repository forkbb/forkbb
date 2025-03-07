      <!-- PRE start -->
      <aside id="fork-debug">
        <!-- PRE inStart -->
        <p class="f-sim-header">{!! __('Debug table') !!}</p>
        <p id="id-fdebugtime">t = {{ num(\microtime(true) - $p->start, 3) }} : q = {{ $p->numQueries}} : m = {{ size(\memory_get_usage()) }} / {{ size(\memory_get_peak_usage()) }}</p>
@if ($p->queries)
        <table id="fork-dgtable">
          <thead id="fork-dgthead">
            <tr>
              <th class="f-tcl" scope="col">{!! __('Query times') !!}</th>
              <th class="f-tcr" scope="col">{!! __('Query') !!}</th>
            </tr>
          </thead>
          <tbody>
    @foreach ($p->queries as $cur)
            <tr>
              <td class="f-tcl">{{ num($cur[1], 3) }}</td>
              <td class="f-tcr">{{ $cur[0] }}</td>
            </tr>
    @endforeach
            <tr>
              <td class="f-tcl">{{ num($p->total, 3) }}</td>
              <td class="f-tcr">{{ num($p->lifeTime, 3) }}</td>
            </tr>
          </tbody>
        </table>
@endif
        <!-- PRE inEnd -->
      </aside>
      <!-- PRE end -->
