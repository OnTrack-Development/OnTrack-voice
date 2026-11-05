/**
 * AYO official portrait visualizer.
 * The user's supplied dot-matrix portrait remains the visual base.
 * Canvas lights follow live PCM output and microphone RMS; no fake speech claim.
 */
export function createAyoAvatar(canvas, container) {
  const ctx = canvas.getContext('2d', { alpha: true });
  if (!ctx) return { setOutput() {}, setMicrophone() {}, setStatus() {}, destroy() {} };
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const random = (seed) => () => ((seed = Math.imul(1664525, seed) + 1013904223 | 0) >>> 0) / 4294967296;
  const rng = random(20261105);
  const particles = Array.from({ length: 62 }, () => ({
    x: .13 + rng() * .74,
    y: .12 + rng() * .75,
    radius: .4 + rng() * 1.9,
    phase: rng() * 6.28,
    speed: .4 + rng() * 1.2,
    red: rng() > .68
  }));
  let width = 340, height = 340, raf = 0, output = null, microphone = null;
  let status = 'idle', activity = 0, lastTime = 0, dead = false;
  const sound = new Uint8Array(2048);
  const microphoneSound = new Uint8Array(2048);
  function resize() {
    const bounds = canvas.getBoundingClientRect();
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    width = Math.max(1, bounds.width);
    height = Math.max(1, bounds.height);
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  function rms(analyser, array) {
    if (!analyser) return 0;
    analyser.getByteTimeDomainData(array);
    let sum = 0;
    for (let i = 0; i < array.length; i += 2) {
      const level = (array[i] - 128) / 128;
      sum += level * level;
    }
    return Math.sqrt(sum / (array.length / 2));
  }
  function draw(t) {
    if (dead) return;
    if (!width || !height) resize();
    const outputLevel = status === 'speaking' ? rms(output, sound) : 0;
    const inputLevel = status === 'listening' ? rms(microphone, microphoneSound) : 0;
    const voiceLevel = Math.max(outputLevel, inputLevel);
    activity += (Math.min(1, voiceLevel * 9.5) - activity) * .22;
    const speaking = status === 'speaking';
    const listening = status === 'listening';
    const factor = speaking ? 1 : listening ? .52 : .22;
    const motion = reduced ? .6 : 1;
    ctx.clearRect(0, 0, width, height);
    const scale = width / 384;
    const centerY = height * .497;
    const left = width * .237, right = width * .759;
    const maxHeight = height * (speaking ? .124 : listening ? .087 : .045);
    const drawPower = (0.18 + activity * 1.4) * factor * motion;
    // A narrow transparent shade hides the original flat mouth-wave just enough
    // for the newly animated points to read as a moving audio signal.
    const shade = ctx.createLinearGradient(0, centerY - 34 * scale, 0, centerY + 34 * scale);
    shade.addColorStop(0, 'rgba(0,0,0,0)');
    shade.addColorStop(.48, 'rgba(0,0,0,.39)');
    shade.addColorStop(.52, 'rgba(0,0,0,.39)');
    shade.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = shade;
    ctx.fillRect(left, centerY - 35 * scale, right-left, 70 * scale);
    const n = 88, step = (right - left) / (n-1), time = t / 1000;
    for (let i=0; i<n; i++) {
      const x = left + step * i;
      const pos = (i/(n-1)) * 2 - 1;
      const envelope = Math.pow(Math.max(0, 1 - Math.abs(pos)), .68);
      const wave = Math.abs(Math.sin(i * .47 - time * (speaking ? 19 : 4)) *
        Math.cos(i * .16 + time * 7) + Math.sin(i * .72 + time * 13) * .33);
      const envelopePeak = (0.26 + wave * .82) * envelope;
      const h = Math.max(2, maxHeight * drawPower * envelopePeak);
      const white = Math.abs(pos) < .18;
      const color = white ? 'rgba(255,255,255,.96)' : 'rgba(255,85,89,.97)';
      ctx.shadowColor = white ? '#fff' : '#fa283a';
      ctx.shadowBlur = 9 + 8 * activity;
      ctx.strokeStyle = color;
      ctx.lineWidth = Math.max(1.2, width / 300);
      ctx.beginPath();
      ctx.moveTo(x, centerY - h);
      ctx.lineTo(x, centerY + h);
      ctx.stroke();
      // Dotted audio spikes mimic the reference portrait's dot-matrix signature.
      ctx.shadowBlur = 6;
      ctx.fillStyle = white ? '#fff' : '#ff4c58';
      const dots = Math.max(1, Math.floor(h/(5*scale)));
      for(let p=-dots;p<=dots;p++){
        ctx.beginPath();
        ctx.arc(x, centerY + p * 5 * scale, 1.15*scale, 0, Math.PI*2);
        ctx.fill();
      }
    }
    ctx.shadowBlur = 0;
    // Small orbiting light particles, brightest while voice is active.
    for (const dot of particles) {
      const shimmer = reduced ? .5 : .5 + .5 * Math.sin(time * dot.speed + dot.phase);
      const xx = (dot.x + (reduced ? 0 : Math.sin(time * .16 + dot.phase) * .006)) * width;
      const yy = (dot.y + (reduced ? 0 : Math.cos(time * .17 + dot.phase) * .007)) * height;
      ctx.globalAlpha = Math.min(.68, (.13 + shimmer * .22) + activity * .22);
      ctx.fillStyle = dot.red ? '#ff4354' : '#fff7f5';
      ctx.beginPath();ctx.arc(xx,yy,dot.radius*scale,0,Math.PI*2);ctx.fill();
    }
    ctx.globalAlpha = 1;
    container.style.setProperty('--voice-level', String(activity.toFixed(3)));
    if (speaking !== container.classList.contains('speaking')) container.classList.toggle('speaking', speaking);
    if (listening !== container.classList.contains('listening')) container.classList.toggle('listening', listening);
    lastTime = t;
    raf = requestAnimationFrame(draw);
  }
  const observer = new ResizeObserver(resize);
  observer.observe(canvas);
  resize();
  raf = requestAnimationFrame(draw);
  return {
    setOutput(analyser) { output = analyser; },
    setMicrophone(analyser) { microphone = analyser; },
    setStatus(value) { status = ['idle','speaking','listening'].includes(value) ? value : 'idle'; },
    destroy() { dead = true; observer.disconnect(); cancelAnimationFrame(raf); }
  };
}
