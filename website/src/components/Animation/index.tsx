import type {ReactNode} from 'react';
import Player from './Player';
import type {Settings} from './scene';
import {asyncRequest} from './scenes/asyncRequest';
import {multipleRequests} from './scenes/multipleRequests';
import {syncRequest} from './scenes/syncRequest';

const scenes = {syncRequest, asyncRequest, multipleRequests};

type Props = {
  scene: keyof typeof scenes;
  /** Settings that differ from the defaults of the scene, e.g. {mode: 'reactive'} */
  settings?: Settings;
};

/**
 * An interactive animation of a use case, to be used in MDX docs:
 *
 * <Animation scene="multipleRequests" settings={{mode: 'reactive'}} />
 */
export default function Animation({scene, settings}: Props): ReactNode {
  return <Player scene={scenes[scene]} settings={settings} />;
}
