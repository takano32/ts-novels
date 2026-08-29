#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Bulk-fetch Wayback captures from a fetch_manifest.json into a staging dir.

Keep-alive connections (N workers), raw id_ mode, follows redirects inside
web.archive.org. Writes raw bytes to <stage>/<repo path>. Failures are logged
to <stage>/_failed.json for a retry pass."""
import http.client, json, os, sys, time, threading, queue
from urllib.parse import urlsplit, quote

STAGE_HOST = 'web.archive.org'
WORKERS = 5

def fetch_one(conn_holder, ts, url, max_hops=6):
    path = f'/web/{ts}id_/{url}'
    host = STAGE_HOST
    for hop in range(max_hops):
        try:
            if conn_holder.get(host) is None:
                conn_holder[host] = http.client.HTTPSConnection(host, timeout=90)
            c = conn_holder[host]
            c.request('GET', quote(path, safe="/:?=&%~@+,;$!*'()"),
                      headers={'User-Agent': 'ts-novels-mirror-restore'})
            r = c.getresponse()
            body = r.read()
            if r.status == 200:
                return body, None
            if r.status in (301, 302, 307, 308):
                loc = r.getheader('Location') or ''
                sp = urlsplit(loc)
                if sp.netloc and sp.netloc != host:
                    return None, f'offsite-redirect:{loc[:80]}'
                path = sp.path + (('?' + sp.query) if sp.query else '')
                continue
            if r.status == 429:
                time.sleep(20)
                continue
            return None, f'http-{r.status}'
        except Exception as e:
            try:
                conn_holder[host].close()
            except Exception:
                pass
            conn_holder[host] = None
            time.sleep(3)
    return None, 'too-many-hops'

def worker(q, stage, results, lock, counter):
    conns = {}
    while True:
        try:
            item = q.get_nowait()
        except queue.Empty:
            return
        body, err = fetch_one(conns, item['ts'], item['url'])
        with lock:
            counter[0] += 1
            n = counter[0]
        if body is not None and len(body) > 0:
            out = os.path.join(stage, item['path'])
            try:
                os.makedirs(os.path.dirname(out), exist_ok=True)
                with open(out, 'wb') as f:
                    f.write(body)
            except OSError as e:
                err = f'write:{e}'
        if body is None or err:
            with lock:
                results.append({**item, 'error': err or 'empty'})
        if n % 100 == 0:
            print(f'  {n} done, {len(results)} failed', flush=True)
        q.task_done()

def main(manifest_path, stage):
    man = json.load(open(manifest_path))
    os.makedirs(stage, exist_ok=True)
    allpaths = set()
    for it in man:
        it['path'] = it['path'].replace('?', '_')
        allpaths.add(it['path'])
    todo = []
    for it in man:
        p = it['path']
        if any(o != p and o.startswith(p + '/') for o in allpaths):
            it['path'] = p + '/index.html'
        if not os.path.isfile(os.path.join(stage, it['path'])):
            todo.append(it)
    print(f'{len(todo)} to fetch (of {len(man)})', flush=True)
    q = queue.Queue()
    for it in todo:
        q.put(it)
    results, lock, counter = [], threading.Lock(), [0]
    threads = [threading.Thread(target=worker, args=(q, stage, results, lock, counter), daemon=True)
               for _ in range(WORKERS)]
    t0 = time.time()
    for t in threads:
        t.start()
    for t in threads:
        t.join()
    json.dump(results, open(os.path.join(stage, '_failed.json'), 'w'), indent=1)
    ok = counter[0] - len(results)
    print(f'DONE fetched={ok} failed={len(results)} in {time.time()-t0:.0f}s', flush=True)

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
