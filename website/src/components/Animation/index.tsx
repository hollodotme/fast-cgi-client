import type {ReactNode} from 'react';
import Player from './Player';
import type {Settings} from './scene';
import {asyncRequest} from './scenes/asyncRequest';
import {callbacks} from './scenes/callbacks';
import {fastcgiBasics} from './scenes/fastcgiBasics';
import {multipleRequests} from './scenes/multipleRequests';
import {passThrough} from './scenes/passThrough';
import {records} from './scenes/records';
import {socketReuse} from './scenes/socketReuse';
import {syncRequest} from './scenes/syncRequest';

const scenes = {
  fastcgiBasics,
  syncRequest,
  asyncRequest,
  socketReuse,
  multipleRequests,
  callbacks,
  passThrough,
  records,
};

type Props = {
  scene: keyof typeof scenes;
  /** Settings that differ from the defaults of the scene, e.g. {mode: 'reactive'} */
  settings?: Settings;
  /** Leaves out the timeline chart and the settings */
  minimal?: boolean;
};

/**
 * An interactive animation of a use case, to be used in MDX docs:
 *
 * <Animation scene="multipleRequests" settings={{mode: 'reactive'}} />
 */
export default function Animation({scene, settings, minimal}: Props): ReactNode {
  return <Player scene={scenes[scene]} settings={settings} minimal={minimal} />;
}
