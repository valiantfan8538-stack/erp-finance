<template>
  <div style="padding: 20px;">
    <h1>仪表盘</h1>
    <el-row :gutter="20">
      <el-col :span="6">
        <el-card>
          <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold;">0</div>
            <div>凭证数量</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card>
          <div style="text-align: center;">
            <div style="font-size: 24px; font-weight: bold;">0</div>
            <div>资产数量</div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
</script>