/* Dendrila Oldweb-kit Ripple: original WebGL1 shader, no libraries, no third-party requests. */
(() => {
  "use strict";
  const host = document.querySelector("[data-dendrila-ripple]");
  if (!host) return;

  const canvas = host.querySelector(".d-ripple-canvas");
  const image = host.querySelector(".d-ripple-poster");
  const control = document.querySelector("[data-dendrila-ripple-toggle]");
  if (!canvas || !image || !control) return;

  const motion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  const speed = Math.min(2, Math.max(0.25, Number(host.dataset.speed) || 1));
  const intensity = Math.min(1, Math.max(0, Number(host.dataset.intensity) || 0.5));
  const offText = control.dataset.pauseLabel || "Mettre le fond en pause";
  const onText = control.dataset.resumeLabel || "Reprendre le fond animé";
  const pointer = [0.5, 0.5];
  const gl = canvas.getContext("webgl", { alpha: false, antialias: false, powerPreference: "low-power" });
  let program, posBuffer, texture, locations, visible = true, paused = false, ready = false;
  let frame = 0, lastDraw = 0, time = 0, previous = 0, width = 0, height = 0;
  let observer;

  const vertex = `
    attribute vec2 a_position;
    varying vec2 v_uv;
    void main() {
      v_uv = a_position * 0.5 + 0.5;
      gl_Position = vec4(a_position, 0.0, 1.0);
    }`;
  const fragment = `
    precision mediump float;
    varying vec2 v_uv;
    uniform sampler2D u_image;
    uniform vec2 u_resolution;
    uniform vec2 u_image_size;
    uniform vec2 u_pointer;
    uniform float u_time;
    uniform float u_speed;
    uniform float u_intensity;
    void main() {
      vec2 view = v_uv;
      float aspect = u_resolution.x / max(u_resolution.y, 1.0);
      vec2 delta = (view - u_pointer) * vec2(aspect, 1.0);
      float radius = length(delta);
      float ripple = sin(radius * 34.0 - u_time * u_speed * 3.5);
      float falloff = exp(-radius * 5.0);
      vec2 direction = normalize(delta + vec2(0.00001));
      vec2 displacement = direction * ripple * falloff * 0.024 * u_intensity;
      displacement += vec2(
        sin(view.y * 20.0 + u_time * u_speed * 0.75),
        cos(view.x * 18.0 - u_time * u_speed * 0.65)
      ) * (0.0025 * u_intensity);
      vec2 warped = view + displacement;
      float imageAspect = u_image_size.x / max(u_image_size.y, 1.0);
      vec2 crop = aspect > imageAspect
        ? vec2(1.0, imageAspect / aspect)
        : vec2(aspect / imageAspect, 1.0);
      vec2 uv = (warped - 0.5) * crop + 0.5;
      gl_FragColor = texture2D(u_image, clamp(uv, 0.001, 0.999));
    }`;

  function shader(type, source) {
    const item = gl.createShader(type);
    gl.shaderSource(item, source);
    gl.compileShader(item);
    if (!gl.getShaderParameter(item, gl.COMPILE_STATUS)) {
      gl.deleteShader(item);
      throw new Error("Ripple shader compilation failed");
    }
    return item;
  }

  function init() {
    if (!gl) return false;
    try {
      const vert = shader(gl.VERTEX_SHADER, vertex);
      const frag = shader(gl.FRAGMENT_SHADER, fragment);
      program = gl.createProgram();
      gl.attachShader(program, vert); gl.attachShader(program, frag); gl.linkProgram(program);
      gl.deleteShader(vert); gl.deleteShader(frag);
      if (!gl.getProgramParameter(program, gl.LINK_STATUS)) throw new Error("Ripple program link failed");
      locations = {
        position: gl.getAttribLocation(program, "a_position"),
        resolution: gl.getUniformLocation(program, "u_resolution"),
        imageSize: gl.getUniformLocation(program, "u_image_size"),
        pointer: gl.getUniformLocation(program, "u_pointer"),
        time: gl.getUniformLocation(program, "u_time"),
        speed: gl.getUniformLocation(program, "u_speed"),
        intensity: gl.getUniformLocation(program, "u_intensity"),
        sampler: gl.getUniformLocation(program, "u_image")
      };
      posBuffer = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, posBuffer);
      gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1,1,-1,-1,1,1,1]), gl.STATIC_DRAW);
      texture = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, texture);
      gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, image);
      gl.useProgram(program);
      gl.uniform1i(locations.sampler, 0);
      gl.uniform2f(locations.imageSize, image.naturalWidth, image.naturalHeight);
      gl.uniform1f(locations.speed, speed);
      gl.uniform1f(locations.intensity, intensity);
      gl.enableVertexAttribArray(locations.position);
      gl.vertexAttribPointer(locations.position, 2, gl.FLOAT, false, 0, 0);
      resize();
      ready = true;
      return true;
    } catch (_) {
      host.dataset.ready = "false";
      return false;
    }
  }

  function resize() {
    const rect = host.getBoundingClientRect();
    if (!rect.width || !rect.height || !gl) return;
    const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
    const scale = Math.min(1, 1280 / Math.max(rect.width * ratio, rect.height * ratio));
    width = Math.max(1, Math.round(rect.width * ratio * scale));
    height = Math.max(1, Math.round(rect.height * ratio * scale));
    if (canvas.width !== width || canvas.height !== height) {
      canvas.width = width; canvas.height = height;
      gl.viewport(0, 0, width, height);
    }
  }

  function permitted() {
    return ready && !motion.matches && !connection?.saveData && !paused && visible && !document.hidden;
  }

  function stop() {
    if (frame) cancelAnimationFrame(frame);
    frame = 0; previous = 0;
  }

  function draw(stamp) {
    frame = 0;
    if (!permitted()) return;
    if (stamp - lastDraw >= 32) {
      time += previous ? Math.min((stamp - previous) / 1000, 0.1) : 0;
      previous = stamp; lastDraw = stamp;
      resize();
      gl.useProgram(program);
      gl.uniform2f(locations.resolution, width, height);
      gl.uniform2f(locations.pointer, pointer[0], pointer[1]);
      gl.uniform1f(locations.time, time);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      host.dataset.ready = "true";
    }
    frame = requestAnimationFrame(draw);
  }

  function sync() {
    if (motion.matches || connection?.saveData || !gl) {
      stop(); host.dataset.ready = "false";
      control.hidden = true;
      return;
    }
    control.hidden = false;
    control.setAttribute("aria-pressed", String(paused));
    control.textContent = paused ? onText : offText;
    if (permitted()) {
      if (!frame) frame = requestAnimationFrame(draw);
    } else {
      stop();
    }
  }

  control.addEventListener("click", () => {
    paused = !paused;
    sync();
  });
  window.addEventListener("pointermove", event => {
    const bounds = host.getBoundingClientRect();
    if (!bounds.width || !bounds.height) return;
    pointer[0] = Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width));
    pointer[1] = 1 - Math.max(0, Math.min(1, (event.clientY - bounds.top) / bounds.height));
  }, { passive: true });
  window.addEventListener("resize", resize, { passive: true });
  document.addEventListener("visibilitychange", sync);
  if (typeof motion.addEventListener === "function") motion.addEventListener("change", sync);
  if (connection?.addEventListener) connection.addEventListener("change", sync);
  canvas.addEventListener("webglcontextlost", event => {
    event.preventDefault(); ready = false; stop(); host.dataset.ready = "false"; control.hidden = true;
  });
  canvas.addEventListener("webglcontextrestored", () => { ready = false; if (init()) sync(); });
  if ("IntersectionObserver" in window) {
    observer = new IntersectionObserver(entries => {
      visible = entries[0]?.isIntersecting ?? false;
      sync();
    });
    observer.observe(host);
  }

  // Never initialize the animated renderer for reduced-motion or data-saving visitors.
  if (motion.matches || connection?.saveData || !gl) {
    control.hidden = true;
    return;
  }
  const load = () => { if (!ready && init()) sync(); };
  if (image.complete && image.naturalWidth) load();
  else image.addEventListener("load", load, { once: true });
  image.addEventListener("error", () => { control.hidden = true; }, { once: true });
})();
