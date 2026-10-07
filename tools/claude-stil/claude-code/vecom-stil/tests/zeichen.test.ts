import { expect, test } from 'claude-code/testing'

const BAND = {
  plugin: 'vecom-stil',
  component: 'AbovePrompt',
  props: {
    hasSurvey: false,
    isWorking: false,
    maxRows: 20,
    bodyColumns: 100,
    scroll: { offset: 0, bodyRows: 19 },
    view: {},
  },
} as const

for (const surface of ['terminal', 'desktop'] as const) {
  test(`${surface}: vor der ersten Eingabe steht das Zeichen samt Schriftzug`, async $ => {
    const band = await $.ui.mount({ ...BAND, surface } as never)
    const baum = JSON.stringify(await band.drawn())

    expect(baum).toContain('V E C O M')
    expect(baum).toContain('#f1d38b')
    expect(baum).toContain('▀')
  })

  test(`${surface}: nach der ersten Eingabe bleibt das Band leer`, async ($, on) => {
    on('prompt.submit', (_$, e) => ({ text: e.text }))
    on('ui.render', { component: 'AbovePrompt' }, () => h('Box', {}, 'eigenes Band') as never)
    await $.prompt.submit({ text: 'Hallo', wait: false, origin: { kind: 'user' } } as never)
    const band = await $.ui.mount({ ...BAND, surface } as never)

    const baum = JSON.stringify(await band.drawn())

    expect(baum).toContain('eigenes Band')
    expect(baum).not.toContain('V E C O M')
  })
}
