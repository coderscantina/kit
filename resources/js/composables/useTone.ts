import { useStorage } from '@vueuse/core'

import { tones, type Tone } from '~/components/ui/emoji/utils'

/** The emoji skin tone the user picked last, remembered across sessions. */
export const useTone = () => {
  const tone = useStorage<Tone>('tone', 'neutral')

  return { tone, tones }
}
