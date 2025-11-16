
---

## assets/vista.js

```javascript
// minimal helpers
async function post(url, data){
  const res = await fetch(url,{method:'POST',body: new URLSearchParams(data)});
  return res.json();
}