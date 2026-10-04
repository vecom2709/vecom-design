"""Bildauftraege fuer die Branchen-Flyer (kie.ai, nano-banana-pro, 2:3, 4K). Gibt JSON aus."""
import json, sys
GRUND = ("Premium cinematic advertising photograph for a web design agency flyer, vertical portrait format. {szene} "
         "Warm golden light, deep rich blacks, high contrast, luxurious elegant mood, photorealistic, full-frame camera, shallow depth of field. "
         "One single continuous scene from top to bottom, single exposure, single viewpoint. The top of the frame is calm and darker, "
         "the bottom third falls off into deep soft shadow because the camera is set low and the nearest area is in darkness. "
         "Absolutely no text, no letters, no numbers, no logos, no signs, no watermark, no screens, no devices with user interface, "
         "no split screen, no diptych, no panels, no frame, no border.")
SZENEN = {
 'handwerk': "A modern luxury house under construction at dusk, warm interior lights, a white safety helmet and rolled blueprints resting on a stone ledge in the foreground.",
 'arztpraxis': "A bright, clean modern medical practice reception with white surfaces, green plants and soft daylight, a stethoscope lying on the counter in the foreground.",
 'immobilien': "A modern luxury villa with an illuminated infinity pool at sunset, palm trees, glowing windows.",
 'kosmetik': "An elegant spa at evening: a woman with closed eyes relaxing during a facial treatment, lit candles and a white orchid, warm soft light.",
 'restaurant': "A fine-dining restaurant table at night with a beautifully plated dish, two glasses of red and white wine, candles and warm bokeh lights.",
 'hotel': "A luxury hotel terrace above the Mediterranean sea at sunset, an elegant lounge sofa with cushions, lanterns, calm water.",
 'fitness': "A modern premium gym in moody low light, rows of dumbbells and a weight bench, dramatic rim light, polished floor.",
 'tourismus': "A Mediterranean coastline like the Amalfi coast with turquoise water, colourful houses on cliffs and a small sandy bay, golden afternoon light.",
 'kanzlei': "Golden scales of justice standing on leather-bound law books on a dark wooden desk in an elegant law office, warm lamp light.",
 'solar': "A modern family house with solar panels on the roof at sunrise, green garden, warm sun flare over the horizon.",
 'industrie': "An industrial robotic arm working in a modern clean factory hall, cool blue light with warm golden highlights, a few sparks.",
 'landwirtschaft': "Wide green fields at sunrise with a red tractor in the distance, morning mist, golden sun on the horizon.",
 'autohaus': "A modern premium car workshop and showroom at night: a car completely hidden under a fitted dark satin car cover in the centre (no part of the car body visible), glossy reflective floor, a clean tool wall and warm golden accent lights.",  # zweimal kam ein echtes Markenauto mit Emblem — darum verhüllt (04.10.2026)
 'steuerberater': "A tidy desk with a calculator, an elegant fountain pen, financial charts on paper documents and reading glasses, warm desk lamp light.",
 'logistik': "A modern truck driving on an open highway at sunset, light trails, mountains in the distance.",
 'lebensmittel': "A rustic wooden table with a bottle of olive oil, fresh vegetables, cheese, bread and herbs, warm window light, Italian kitchen mood.",
 'shop': "An elegant fashion boutique interior with clothing racks, warm spotlights, wooden floor and a mirror.",
 'bildung': "A cosy study desk with stacked books, an open notebook, a pen and a warm desk lamp, bookshelves softly blurred behind.",
 'foto': "A professional camera with a large lens on a dark table, beautiful golden bokeh lights in the background.",
 'events': "A concert crowd with raised hands in front of a big stage with golden and purple spotlights and haze.",
 'caravan': "A modern motorhome parked at a lakeside campsite at dusk, warm lights inside, pine trees and calm water.",
 'reinigung': "A modern glass office building with spotless shining windows at golden hour, clear sky reflections.",
 'sicherheit': "A security camera mounted on the corner of a modern building facade at night, soft blue and golden light.",
 'tierarzt': "A golden retriever and a tabby cat sitting together on green grass in warm evening sunlight, friendly and calm.",
}
nur = sys.argv[1:]
print(json.dumps([{'name': k, 'prompt': GRUND.format(szene=v)} for k, v in SZENEN.items() if not nur or k in nur], ensure_ascii=False, indent=1))
