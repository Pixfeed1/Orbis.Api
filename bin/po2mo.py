"""Minimal PO -> MO compiler (handles msgctxt and plurals)."""
import re, struct, sys

def unquote(lines):
    out = []
    for l in lines:
        m = re.match(r'^\s*"(.*)"\s*$', l)
        if m:
            out.append(m.group(1))
    raw = ''.join(out)
    return raw.encode('utf-8').decode('unicode_escape').encode('latin-1').decode('utf-8')

def parse(path):
    entries, cur, key = {}, {}, None
    buf = []
    def flush():
        nonlocal key, buf
        if key:
            cur[key] = unquote(buf)
        key, buf = None, []
    for line in open(path, encoding='utf-8'):
        line = line.rstrip('\n')
        if line.startswith('#') or line.strip() == '':
            if line.strip() == '':
                flush()
                if 'msgid' in cur:
                    entries[mkkey(cur)] = mkval(cur)
                cur = {}
            continue
        m = re.match(r'^(msgctxt|msgid_plural|msgid|msgstr\[\d\]|msgstr)\s+(".*")$', line)
        if m:
            flush()
            key = m.group(1)
            buf = [m.group(2)]
        elif line.startswith('"'):
            buf.append(line)
    flush()
    if 'msgid' in cur:
        entries[mkkey(cur)] = mkval(cur)
    return entries

def mkkey(e):
    k = e['msgid']
    if 'msgid_plural' in e:
        k = k + '\x00' + e['msgid_plural']
    if 'msgctxt' in e:
        k = e['msgctxt'] + '\x04' + k
    return k

def mkval(e):
    plurals = sorted(k for k in e if k.startswith('msgstr['))
    if plurals:
        return '\x00'.join(e[k] for k in plurals)
    return e.get('msgstr', '')

def write_mo(entries, path):
    pairs = [(k.encode('utf-8'), v.encode('utf-8')) for k, v in entries.items() if v != '' or k == '']
    pairs.sort(key=lambda kv: kv[0])
    keys = [k for k, _ in pairs]
    vals = [v for _, v in pairs]
    items = pairs
    n = len(items)
    off_o = 28
    off_t = off_o + n * 8
    start = off_t + n * 8
    otab, ttab, data = [], [], b''
    for k in keys:
        otab.append((len(k), start + len(data)))
        data += k + b'\x00'
    for v in vals:
        ttab.append((len(v), start + len(data)))
        data += v + b'\x00'
    out = struct.pack('<Iiiiiii', 0x950412de, 0, n, off_o, off_t, 0, 0)
    for l, o in otab:
        out += struct.pack('<ii', l, o)
    for l, o in ttab:
        out += struct.pack('<ii', l, o)
    open(path, 'wb').write(out + data)
    return n

e = parse(sys.argv[1])
print('entrees compilees:', write_mo(e, sys.argv[2]))
