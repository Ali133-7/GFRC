import client from './client';

export const systemApi = {
  export: () =>
    client
      .get('/system/export', { responseType: 'blob' })
      .then((r) => r.data as Blob),

  import: (file: File) => {
    const reader = new FileReader();
    return new Promise<null>((resolve, reject) => {
      reader.onload = async () => {
        try {
          const json = JSON.parse(reader.result as string);
          const res = await client.post<null>('/system/import', json);
          resolve(res.data);
        } catch (e) {
          reject(e);
        }
      };
      reader.onerror = reject;
      reader.readAsText(file);
    });
  },

  uploadLogo: (file: File) => {
    const formData = new FormData();
    formData.append('logo', file);
    return client.post<{ url: string }>('/system/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then((r) => r.data);
  },
};
