import { atom, read, update } from 'claude-code'
import type { Register } from 'claude-code'

import type { Begruesst } from '../types'

import { ZEICHEN } from './zeichen'

// Die Farben von vecom-design.it, dieselben wie in claude-ai.user.css und im
// Terminal-Schema. Gold heißt genau eine Sache: das, was gerade läuft.
const GOLD = '#f1d38b'
const ELFENBEIN = '#f7f3ea'
const LEISE = '#8b847a'

// Was Claude gerade tut, in den Worten der Werkstatt statt „Sauteing“.
const WOERTER = [
  'Gestaltet',
  'Setzt',
  'Feilt',
  'Poliert',
  'Vergoldet',
  'Misst nach',
  'Prüft die Kette',
  'Rückt zurecht',
  'Wägt ab',
  'Liest nach',
]

// Die Zeile nach dem Zug („Baked for 3s“) – ein Wort, das zu Fertigem passt.
const FERTIG = ['Vollendet', 'Gesetzt', 'Geprüft', 'Fertig']

// Bis zur ersten Eingabe steht das Zeichen über dem Prompt, danach nicht mehr:
// Ein Logo, das bei jeder Zeile mitläuft, wird zu Rauschen (Regel 3, CLAUDE.md).
const begruesst = atom({ plugin: 'vecom-stil', key: 'begruesst' } as const, false as Begruesst)

const wahl = (liste: readonly string[], i = Math.random() * liste.length) => liste[Math.floor(i) % liste.length] ?? 'Gestaltet'

export const register: Register = on => {
  // Je Zug ein Wort, nicht je Bild – sonst flackert es bei jedem Neuzeichnen.
  let wort = wahl(WOERTER)

  on('prompt.submit', async ($, e, next) => {
    wort = wahl(WOERTER)
    await update($, begruesst, () => true)

    return next(e)
  }).catch(($, e, next) => next(e))

  on('ui.render', { component: 'Spinner' }, ($, e, next) =>
    e.props.message === null ? next({ ...e, props: { ...e.props, word: wort } }) : next(e),
  )

  // Aus der Dauer abgeleitet, nicht gewürfelt: Die Zeile wird beim Blättern neu
  // gezeichnet und soll dabei nicht das Wort wechseln.
  on('ui.render', { component: 'TurnDuration' }, ($, e, next) =>
    next({ ...e, props: { ...e.props, word: wahl(FERTIG, e.props.durationMs / 1000) } }),
  )

  on('ui.render', { component: 'PromptHint' }, ($, e, next) =>
    e.props.isWorking ? next(e) : next({ ...e, props: { ...e.props, tail: '  ◆ vecom design' } }),
  )

  on('ui.render', { component: 'AbovePrompt' }, async ($, e, next) => {
    if (e.props.hasSurvey || (await read($, begruesst)) || e.props.maxRows < ZEICHEN.length + 1) {
      return next(e)
    }

    const { Box, Text } = $.ui.resolve(e)
    const schmal = e.props.bodyColumns < 48

    const zeichen = (
      <Box flexDirection="column">
        {ZEICHEN.map((reihe, y) => (
          <Box key={`z${y}`}>
            {reihe.map(([oben, unten], x) =>
              oben && unten ? (
                <Text key={`${x}`} color={oben} backgroundColor={unten}>▀</Text>
              ) : oben ? (
                <Text key={`${x}`} color={oben}>▀</Text>
              ) : unten ? (
                <Text key={`${x}`} color={unten}>▄</Text>
              ) : (
                <Text key={`${x}`}> </Text>
              ),
            )}
          </Box>
        ))}
      </Box>
    )

    if (schmal) {
      return zeichen
    }

    return (
      <Box gap={3} alignItems="center">
        {zeichen}
        <Box flexDirection="column">
          <Text color={ELFENBEIN} bold>V E C O M</Text>
          <Text color={GOLD}>D E S I G N</Text>
          <Text> </Text>
          <Text color={LEISE}>Webdesign aus Sizilien · vecom-design.it</Text>
        </Box>
      </Box>
    )
  })
}
