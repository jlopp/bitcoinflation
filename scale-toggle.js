/*
 * addYAxisScaleToggle - adds a "Y Axis Scale" Linear/Log slider switch to a
 * Plotly chart. The switch is a normal DOM control placed at the bottom-left
 * of the chart's container (the .chart-card), below the plot, so it never
 * overlaps the chart itself.
 *
 * Call it right after Plotly.newPlot():
 *
 *   addYAxisScaleToggle('chart');                          // single y axis, log default
 *   addYAxisScaleToggle('chart', { default: 'linear' });   // linear default
 *   addYAxisScaleToggle('chart', { axes: ['y', 'y2'] });   // dual axis chart
 *
 * opts:
 *   axes    {string[]}        Axis ids to control (default ['y']).
 *   default {'log'|'linear'}  Scale selected on load (default 'log').
 *
 * Switching flips type, dtick and hoverformat together, and re-runs autorange
 * so the tick values are recomputed for the new scale (the log charts use
 * integer ticks and 4-significant-digit hover values, which read badly on a
 * linear axis, so linear gets auto ticks and 2-decimal hover values instead).
 */
function addYAxisScaleToggle(divId, opts) {
  if (typeof Plotly === 'undefined') {
    return;
  }
  opts = opts || {};
  var axes = opts.axes || ['y'];
  var state = opts['default'] === 'linear' ? 'linear' : 'log';

  // relayout needs the full axis name: 'y' -> 'yaxis', 'y2' -> 'yaxis2'.
  // The shorthand 'y.type' throws inside Plotly's relayout, so it must not be used.
  function axisName(id) {
    return 'yaxis' + id.slice(1);
  }

  function ensureStyles() {
    if (document.getElementById('yax-toggle-style')) {
      return;
    }
    var css =
      '.yax-toggle{' +
        'display:inline-flex;align-items:center;gap:10px;' +
        'margin:8px 2px 0;padding:5px 10px 5px 12px;' +
        'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;' +
        'font-size:12px;line-height:1;color:#5b616e;' +
        'background:rgba(0,0,0,0.03);border:1px solid rgba(0,0,0,0.10);' +
        'border-radius:999px;user-select:none;-webkit-user-select:none;' +
      '}' +
      '.yax-toggle__title{font-weight:600;letter-spacing:0.01em;white-space:nowrap;}' +
      '.yax-toggle__switch{' +
        'position:relative;display:inline-flex;align-items:stretch;' +
        'width:132px;padding:2px;margin:0;cursor:pointer;' +
        'background:rgba(0,0,0,0.06);border:1px solid rgba(0,0,0,0.12);' +
        'border-radius:999px;font:inherit;color:inherit;' +
        '-webkit-appearance:none;appearance:none;' +
      '}' +
      '.yax-toggle__switch:focus-visible{outline:2px solid #f7931a;outline-offset:2px;}' +
      '.yax-toggle__opt{' +
        'position:relative;z-index:2;flex:1 1 0;text-align:center;' +
        'padding:4px 0;border-radius:999px;font-size:11px;font-weight:600;' +
        'color:#5b616e;transition:color .18s ease;white-space:nowrap;' +
      '}' +
      '.yax-toggle__thumb{' +
        'position:absolute;top:2px;bottom:2px;left:2px;right:50%;z-index:1;' +
        'background:#f7931a;border-radius:999px;' +
        'transition:left .18s ease,right .18s ease;' +
      '}' +
      '.yax-toggle.is-log .yax-toggle__thumb{left:50%;right:2px;}' +
      '.yax-toggle.is-linear .yax-toggle__opt[data-scale="linear"],' +
      '.yax-toggle.is-log .yax-toggle__opt[data-scale="log"]{color:#fff;}' +
      '@media (prefers-color-scheme:dark){' +
        '.yax-toggle{background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.12);color:#9aa0ab;}' +
        '.yax-toggle__opt{color:#9aa0ab;}' +
        '.yax-toggle__switch{background:rgba(255,255,255,0.07);border-color:rgba(255,255,255,0.16);}' +
      '}';
    var style = document.createElement('style');
    style.id = 'yax-toggle-style';
    style.textContent = css;
    document.head.appendChild(style);
  }

  function applyScale(type) {
    var update = {};
    axes.forEach(function (axis) {
      var name = axisName(axis);
      update[name + '.type'] = type;
      update[name + '.dtick'] = type === 'log' ? 1 : null;
      update[name + '.hoverformat'] = type === 'log' ? '.4r' : ',.2f';
      // Clear any cached ticks and re-autorange so the values are recomputed
      // for the new scale instead of reusing the previous axis' tick set.
      update[name + '.tickmode'] = 'auto';
      update[name + '.tickvals'] = null;
      update[name + '.ticktext'] = null;
      update[name + '.autorange'] = true;
    });
    Plotly.relayout(divId, update).then(function () {
      // tick label widths change a lot between scales; resize recomputes the
      // margins so labels are not clipped out of the container
      Plotly.Plots.resize(divId);
    });
  }

  var gd = document.getElementById(divId);
  if (!gd) {
    return;
  }

  ensureStyles();

  // remove any previous control so repeated calls don't stack up
  var existing = gd.parentNode.querySelector('.yax-toggle');
  if (existing) {
    existing.parentNode.removeChild(existing);
  }

  var wrap = document.createElement('div');
  wrap.className = 'yax-toggle is-' + state;

  var title = document.createElement('span');
  title.className = 'yax-toggle__title';
  title.textContent = 'Y Axis Scale';

  var sw = document.createElement('button');
  sw.type = 'button';
  sw.className = 'yax-toggle__switch';
  sw.setAttribute('role', 'switch');
  sw.setAttribute('aria-label', 'Y Axis Scale');

  var thumb = document.createElement('span');
  thumb.className = 'yax-toggle__thumb';
  thumb.setAttribute('aria-hidden', 'true');

  var optLinear = document.createElement('span');
  optLinear.className = 'yax-toggle__opt';
  optLinear.setAttribute('data-scale', 'linear');
  optLinear.textContent = 'Linear';

  var optLog = document.createElement('span');
  optLog.className = 'yax-toggle__opt';
  optLog.setAttribute('data-scale', 'log');
  optLog.textContent = 'Log';

  sw.appendChild(thumb);
  sw.appendChild(optLinear);
  sw.appendChild(optLog);
  wrap.appendChild(title);
  wrap.appendChild(sw);

  function syncUi() {
    wrap.classList.toggle('is-linear', state === 'linear');
    wrap.classList.toggle('is-log', state === 'log');
    sw.setAttribute('aria-checked', state === 'log' ? 'true' : 'false');
  }

  function toggle() {
    state = state === 'log' ? 'linear' : 'log';
    syncUi();
    applyScale(state);
  }

  sw.addEventListener('click', toggle);
  sw.addEventListener('keydown', function (e) {
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault();
      toggle();
    }
  });

  syncUi();
  gd.parentNode.appendChild(wrap);
}
