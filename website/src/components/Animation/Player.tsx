import {useEffect, useMemo, useRef, useState, type ReactNode} from 'react';
import {
  animate,
  AnimatePresence,
  motion,
  useAnimationFrame,
  useInView,
  useMotionValue,
  useMotionValueEvent,
  useReducedMotion,
  type AnimationPlaybackControls,
} from 'motion/react';
import CodeBlock from '@theme/CodeBlock';
import {defaultSettings, type Scene, type Settings} from './scene';
import {captionAt, codeAt, highlightMetastring, outputAt, stepsOf} from './timeline';
import Icon from './Icon';
import Stage from './Stage';
import Gantt, {legendOf} from './Gantt';
import styles from './styles.module.css';

const SPEEDS = [0.5, 1, 2];

type Props = {
  scene: Scene;
  /** Settings that differ from the defaults of the scene */
  settings?: Settings;
  /** Leaves out the timeline chart and the settings, e.g. on the homepage */
  minimal?: boolean;
};

/** Plays a scene: the stage, a caption per step, controls, the timeline chart, the example code and its output */
export default function Player({scene, settings: initial = {}, minimal = false}: Props): ReactNode {
  const [settings, setSettings] = useState<Settings>(() => ({...defaultSettings(scene), ...initial}));
  const timeline = useMemo(() => scene.build(settings), [scene, settings]);
  const code = useMemo(() => scene.code(settings), [scene, settings]);
  const steps = useMemo(() => stepsOf(timeline), [timeline]);

  const clock = useMotionValue(0);
  const [time, setTime] = useState(0);
  useMotionValueEvent(clock, 'change', setTime);

  const [playing, setPlaying] = useState(false);
  const [speed, setSpeed] = useState(1);
  const seeking = useRef<AnimationPlaybackControls | null>(null);

  const reduceMotion = useReducedMotion() ?? false;
  const container = useRef<HTMLDivElement>(null);
  const stage = useRef<HTMLDivElement>(null);
  const inView = useInView(stage, {once: true, amount: 0.8});
  const [compact, setCompact] = useState(false);

  // Switch to the compact layout of stage and chart on narrow screens
  useEffect(() => {
    const element = container.current;
    if (element === null) {
      return undefined;
    }
    const observer = new ResizeObserver(([entry]) => setCompact(entry.contentRect.width < 600));
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  // Start playing when the animation scrolls into view, unless the reader prefers reduced motion
  useEffect(() => {
    if (inView && !reduceMotion) {
      setPlaying(true);
    }
  }, [inView, reduceMotion]);

  useAnimationFrame((_, delta) => {
    if (!playing) {
      return;
    }
    // Limit the step, e.g. after the browser tab was in the background
    const next = Math.min(timeline.duration, clock.get() + (Math.min(delta, 100) / 1000) * speed);
    clock.set(next);
    if (next >= timeline.duration) {
      setPlaying(false);
    }
  });

  const stopSeeking = () => {
    seeking.current?.stop();
    seeking.current = null;
  };

  // Stop a running seek animation when the player is removed
  useEffect(() => () => seeking.current?.stop(), []);

  const seek = (target: number, smooth = true) => {
    stopSeeking();
    setPlaying(false);
    if (smooth && !reduceMotion) {
      seeking.current = animate(clock, target, {duration: 0.45, ease: 'easeInOut'});
    } else {
      clock.set(target);
    }
  };

  const togglePlaying = () => {
    stopSeeking();
    if (!playing && clock.get() >= timeline.duration) {
      clock.set(0);
    }
    setPlaying(!playing);
  };

  const previousStep = () => seek([...steps].reverse().find((step) => step < time - 0.05) ?? 0);
  const nextStep = () => seek(steps.find((step) => step > time + 0.05) ?? timeline.duration);

  const change = (id: string, value: number | string) => {
    stopSeeking();
    setSettings((current) => ({...current, [id]: value}));
    clock.set(0);
    setPlaying(!reduceMotion);
  };

  const caption = captionAt(timeline, time);
  const stepNumber = steps.filter((step) => step <= time).length;
  const highlight = highlightMetastring(code, codeAt(timeline, time));
  const output = outputAt(timeline, time);
  const legend = useMemo(() => legendOf(timeline), [timeline]);
  const filename = typeof scene.filename === 'function' ? scene.filename(settings) : scene.filename;

  return (
    <div ref={container} className={styles.player}>
      <p className={styles.caption} aria-live="polite">
        <span className={styles.stepNumber}>
          {stepNumber}/{steps.length}
        </span>
        <AnimatePresence mode="wait" initial={false}>
          <motion.span
            key={caption}
            initial={{opacity: 0, y: reduceMotion ? 0 : 4}}
            animate={{opacity: 1, y: 0}}
            exit={{opacity: 0}}
            transition={{duration: 0.2}}>
            {caption}
          </motion.span>
        </AnimatePresence>
      </p>

      <div ref={stage}>
        <Stage timeline={timeline} time={time} summary={scene.summary} compact={compact} />
      </div>

      <div className={styles.controls}>
        <button type="button" className={styles.button} onClick={() => seek(0, false)} aria-label="Restart">
          <Icon name="restart" />
        </button>
        <button type="button" className={styles.button} onClick={previousStep} aria-label="Previous step">
          <Icon name="previous" />
        </button>
        <button
          type="button"
          className={`${styles.button} ${styles.play}`}
          onClick={togglePlaying}
          aria-label={playing ? 'Pause' : 'Play'}>
          <Icon name={playing ? 'pause' : 'play'} />
        </button>
        <button type="button" className={styles.button} onClick={nextStep} aria-label="Next step">
          <Icon name="next" />
        </button>
        <input
          className={styles.scrubber}
          type="range"
          min={0}
          max={timeline.duration}
          step={0.01}
          value={time}
          onChange={(event) => seek(Number(event.target.value), false)}
          aria-label="Time"
        />
        <span className={styles.clock}>{time.toFixed(1)} s</span>
        <span className={styles.speed}>
          <select value={speed} onChange={(event) => setSpeed(Number(event.target.value))} aria-label="Speed">
            {SPEEDS.map((value) => (
              <option key={value} value={value}>
                {value}×
              </option>
            ))}
          </select>
          <Icon name="chevron" className={styles.speedChevron} />
        </span>
      </div>

      {!minimal && (
        <>
      <Gantt timeline={timeline} time={time} onSeek={(target) => seek(target)} compact={compact} />
      <ul className={styles.legend}>
        {legend.map((entry) => (
          <li key={entry.kind} data-kind={entry.kind}>
            {entry.label}
          </li>
        ))}
      </ul>

      {(scene.sliders.length > 0 || scene.choices.length > 0) && (
        <fieldset className={styles.settings}>
          <legend>Try it: change the example</legend>
          {scene.choices.map((choice) => (
            <div key={choice.id} className={styles.choice} role="group" aria-label={choice.label}>
              <span>{choice.label}</span>
              {choice.options.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  aria-pressed={settings[choice.id] === option.value}
                  className={styles.option}
                  onClick={() => change(choice.id, option.value)}>
                  {option.label}
                </button>
              ))}
            </div>
          ))}
          {scene.sliders.map((slider) => (
            <label key={slider.id} className={styles.slider}>
              <span>
                {slider.label}: <strong>{`${settings[slider.id]} ${slider.unit}`}</strong>
              </span>
              <input
                type="range"
                min={slider.min}
                max={slider.max}
                step={slider.step}
                value={Number(settings[slider.id])}
                onChange={(event) => change(slider.id, Number(event.target.value))}
              />
            </label>
          ))}
        </fieldset>
      )}
        </>
      )}

      <div className={styles.panels}>
        <CodeBlock language={scene.language?.(settings) ?? 'php'} title={filename} metastring={highlight} showLineNumbers>
          {code.map((line) => line.text).join('\n')}
        </CodeBlock>
        <div className={styles.console}>
          <div className={styles.consoleTitle}>{scene.outputTitle?.(settings) ?? `$ php ${filename}`}</div>
          <pre className={styles.consoleOutput}>
            {output}
            <span className={styles.cursor} aria-hidden="true">
              ▍
            </span>
          </pre>
        </div>
      </div>
    </div>
  );
}
