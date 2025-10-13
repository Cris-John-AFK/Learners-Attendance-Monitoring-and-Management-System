// vite.config.js
import { PrimeVueResolver } from "file:///C:/xampp/htdocs/CAPSTONE%202/sakai_lamms/node_modules/@primevue/auto-import-resolver/index.mjs";
import vue from "file:///C:/xampp/htdocs/CAPSTONE%202/sakai_lamms/node_modules/@vitejs/plugin-vue/dist/index.mjs";
import { fileURLToPath, URL } from "node:url";
import Components from "file:///C:/xampp/htdocs/CAPSTONE%202/sakai_lamms/node_modules/unplugin-vue-components/dist/vite.js";
import { defineConfig } from "file:///C:/xampp/htdocs/CAPSTONE%202/sakai_lamms/node_modules/vite/dist/node/index.js";
var __vite_injected_original_import_meta_url = "file:///C:/xampp/htdocs/CAPSTONE%202/sakai_lamms/vite.config.js";
var vite_config_default = defineConfig({
  plugins: [
    vue(),
    Components({
      resolvers: [PrimeVueResolver()]
    })
  ],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", __vite_injected_original_import_meta_url))
    }
  },
  optimizeDeps: {
    include: ["vue-qrcode-reader"]
  },
  server: {
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true
      }
    }
  }
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJDOlxcXFx4YW1wcFxcXFxodGRvY3NcXFxcQ0FQU1RPTkUgMlxcXFxzYWthaV9sYW1tc1wiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9maWxlbmFtZSA9IFwiQzpcXFxceGFtcHBcXFxcaHRkb2NzXFxcXENBUFNUT05FIDJcXFxcc2FrYWlfbGFtbXNcXFxcdml0ZS5jb25maWcuanNcIjtjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfaW1wb3J0X21ldGFfdXJsID0gXCJmaWxlOi8vL0M6L3hhbXBwL2h0ZG9jcy9DQVBTVE9ORSUyMDIvc2FrYWlfbGFtbXMvdml0ZS5jb25maWcuanNcIjsvLyB2aXRlLmNvbmZpZy5qc1xyXG5pbXBvcnQgeyBQcmltZVZ1ZVJlc29sdmVyIH0gZnJvbSAnQHByaW1ldnVlL2F1dG8taW1wb3J0LXJlc29sdmVyJztcclxuaW1wb3J0IHZ1ZSBmcm9tICdAdml0ZWpzL3BsdWdpbi12dWUnO1xyXG5pbXBvcnQgeyBmaWxlVVJMVG9QYXRoLCBVUkwgfSBmcm9tICdub2RlOnVybCc7XHJcbmltcG9ydCBDb21wb25lbnRzIGZyb20gJ3VucGx1Z2luLXZ1ZS1jb21wb25lbnRzL3ZpdGUnO1xyXG5pbXBvcnQgeyBkZWZpbmVDb25maWcgfSBmcm9tICd2aXRlJztcclxuXHJcbi8vIGh0dHBzOi8vdml0ZWpzLmRldi9jb25maWcvXHJcbmV4cG9ydCBkZWZhdWx0IGRlZmluZUNvbmZpZyh7XHJcbiAgICBwbHVnaW5zOiBbXHJcbiAgICAgICAgdnVlKCksXHJcbiAgICAgICAgQ29tcG9uZW50cyh7XHJcbiAgICAgICAgICAgIHJlc29sdmVyczogW1ByaW1lVnVlUmVzb2x2ZXIoKV1cclxuICAgICAgICB9KVxyXG4gICAgXSxcclxuICAgIHJlc29sdmU6IHtcclxuICAgICAgICBhbGlhczoge1xyXG4gICAgICAgICAgICAnQCc6IGZpbGVVUkxUb1BhdGgobmV3IFVSTCgnLi9zcmMnLCBpbXBvcnQubWV0YS51cmwpKVxyXG4gICAgICAgIH1cclxuICAgIH0sXHJcbiAgICBvcHRpbWl6ZURlcHM6IHtcclxuICAgICAgICBpbmNsdWRlOiBbJ3Z1ZS1xcmNvZGUtcmVhZGVyJ11cclxuICAgIH0sXHJcbiAgICBzZXJ2ZXI6IHtcclxuICAgICAgICBwcm94eToge1xyXG4gICAgICAgICAgICAnL2FwaSc6IHtcclxuICAgICAgICAgICAgICAgIHRhcmdldDogJ2h0dHA6Ly9sb2NhbGhvc3Q6ODAwMCcsXHJcbiAgICAgICAgICAgICAgICBjaGFuZ2VPcmlnaW46IHRydWVcclxuICAgICAgICAgICAgfVxyXG4gICAgICAgIH1cclxuICAgIH1cclxufSk7XHJcbiJdLAogICJtYXBwaW5ncyI6ICI7QUFDQSxTQUFTLHdCQUF3QjtBQUNqQyxPQUFPLFNBQVM7QUFDaEIsU0FBUyxlQUFlLFdBQVc7QUFDbkMsT0FBTyxnQkFBZ0I7QUFDdkIsU0FBUyxvQkFBb0I7QUFMZ0ssSUFBTSwyQ0FBMkM7QUFROU8sSUFBTyxzQkFBUSxhQUFhO0FBQUEsRUFDeEIsU0FBUztBQUFBLElBQ0wsSUFBSTtBQUFBLElBQ0osV0FBVztBQUFBLE1BQ1AsV0FBVyxDQUFDLGlCQUFpQixDQUFDO0FBQUEsSUFDbEMsQ0FBQztBQUFBLEVBQ0w7QUFBQSxFQUNBLFNBQVM7QUFBQSxJQUNMLE9BQU87QUFBQSxNQUNILEtBQUssY0FBYyxJQUFJLElBQUksU0FBUyx3Q0FBZSxDQUFDO0FBQUEsSUFDeEQ7QUFBQSxFQUNKO0FBQUEsRUFDQSxjQUFjO0FBQUEsSUFDVixTQUFTLENBQUMsbUJBQW1CO0FBQUEsRUFDakM7QUFBQSxFQUNBLFFBQVE7QUFBQSxJQUNKLE9BQU87QUFBQSxNQUNILFFBQVE7QUFBQSxRQUNKLFFBQVE7QUFBQSxRQUNSLGNBQWM7QUFBQSxNQUNsQjtBQUFBLElBQ0o7QUFBQSxFQUNKO0FBQ0osQ0FBQzsiLAogICJuYW1lcyI6IFtdCn0K
