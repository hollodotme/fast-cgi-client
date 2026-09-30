import type {CodeLine, Timeline} from './timeline';

/** A number the reader can change with a slider, e.g. the runtime of a script */
export type Slider = {
  id: string;
  label: string;
  min: number;
  max: number;
  step: number;
  default: number;
  unit: string;
};

/** A choice between variants of a scene, e.g. two ways of reading responses */
export type Choice = {
  id: string;
  label: string;
  options: {value: string; label: string}[];
  default: string;
};

export type Settings = Record<string, number | string>;

export type Scene = {
  /** Describes the scene for screen readers */
  summary: string;
  /** File name shown above the code */
  filename: string;
  sliders: Slider[];
  choices: Choice[];
  code: (settings: Settings) => CodeLine[];
  build: (settings: Settings) => Timeline;
};

export function defaultSettings(scene: Scene): Settings {
  return Object.fromEntries([
    ...scene.sliders.map((slider): [string, number] => [slider.id, slider.default]),
    ...scene.choices.map((choice): [string, string] => [choice.id, choice.default]),
  ]);
}

/** Builds code lines from lines of text, where a line ending with a comment like `//@send` gets that tag */
export function php(text: string): CodeLine[] {
  return text
    .replace(/^\n+|\s+$/g, '')
    .split('\n')
    .map((line) => {
      const match = /^(.*?)\s*\/\/@([\w-]+)$/.exec(line);
      return match ? {text: match[1], tag: match[2]} : {text: line};
    });
}
