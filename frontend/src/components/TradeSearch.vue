<template>
  <div class="trade-search">
    <div class="search-panel">
      <h2 class="panel-title">交易信息查询</h2>
      
      <el-form :model="searchForm" label-position="top" class="search-form">
        <div class="form-row">
          <!-- 地区 -->
          <el-form-item label="地区" class="form-item-wide">
            <div class="region-selector">
              <div v-for="(region, index) in searchForm.regions" :key="index" class="region-row">
                <el-cascader
                  v-model="region.value"
                  :options="regionOptions"
                  :props="{ multiple: true, emitPath: true, checkStrictly: true }"
                  placeholder="选择省份/城市"
                  clearable
                  collapse-tags
                  :show-all-levels="false"
                  style="width: 400px"
                />
                <el-button type="danger" size="small" circle @click="removeRegion(index)" :disabled="searchForm.regions.length === 1">
                  <el-icon><Delete /></el-icon>
                </el-button>
              </div>
              <el-button type="primary" size="small" @click="addRegion">
                <el-icon><Plus /></el-icon> 添加地区
              </el-button>
            </div>
          </el-form-item>
        </div>

        <div class="form-row">
          <!-- 项目名称 -->
          <el-form-item label="项目名称" class="form-item-wide">
            <el-input
              v-model="searchForm.projectKeywords"
              type="textarea"
              :rows="2"
              placeholder="可输入多个关键词，用换行分隔"
              clearable
            />
          </el-form-item>
        </div>

        <div class="form-row">
          <!-- 业务类型 -->
          <el-form-item label="业务类型">
            <el-select v-model="searchForm.ywlxIds" multiple collapse-tags placeholder="选择业务类型" clearable style="width: 220px">
              <el-option v-for="item in dicts.ywlx" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>

          <!-- 标讯类型 -->
          <el-form-item label="标讯类型">
            <el-select v-model="searchForm.xxlxIds" multiple collapse-tags placeholder="选择标讯类型" clearable style="width: 220px">
              <el-option v-for="item in dicts.xxlx" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>

          <!-- 日期范围 -->
          <el-form-item label="日期范围">
            <el-date-picker
              v-model="searchForm.dateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="YYYY-MM-DD"
              clearable
            />
          </el-form-item>
        </div>

        <div class="form-row">
          <!-- 金额范围 -->
          <el-form-item label="金额范围（万元）">
            <div class="money-range">
              <el-input-number v-model="searchForm.moneyMin" placeholder="最小金额" :controls="false" :precision="2" style="width: 140px" />
              <span class="range-separator">-</span>
              <el-input-number v-model="searchForm.moneyMax" placeholder="最大金额" :controls="false" :precision="2" style="width: 140px" />
              <el-checkbox v-model="searchForm.includeEmptyMoney" style="margin-left: 12px">包含金额为空的项目</el-checkbox>
            </div>
          </el-form-item>

          <!-- 采购方式 -->
          <el-form-item label="采购方式">
            <el-select v-model="searchForm.cgfsIds" multiple collapse-tags placeholder="选择采购方式" clearable style="width: 220px">
              <el-option v-for="item in dicts.cgfs" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>

          <!-- 采购品类 -->
          <el-form-item label="采购品类">
            <el-select v-model="searchForm.cgplIds" multiple collapse-tags placeholder="选择采购品类" clearable style="width: 220px">
              <el-option v-for="item in dicts.cgpl" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
        </div>

        <div class="form-row">
          <!-- 招标类型 -->
          <el-form-item label="招标类型">
            <el-select v-model="searchForm.zblxIds" multiple collapse-tags placeholder="选择招标类型" clearable style="width: 220px">
              <el-option v-for="item in dicts.zblx" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>

          <!-- 工程类型 -->
          <el-form-item label="工程类型">
            <el-select v-model="searchForm.gclxIds" multiple collapse-tags placeholder="选择工程类型" clearable style="width: 220px">
              <el-option v-for="item in dicts.gclx" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
        </div>

        <!-- 企业条件组 -->
        <div class="company-section">
          <div class="section-header">
            <h3>企业条件</h3>
            <span class="section-desc">多组企业之间为"或"关系，一组内企业名称与角色为"且"关系</span>
          </div>
          <div v-for="(group, index) in searchForm.companyGroups" :key="index" class="company-group">
            <div class="group-label">条件组 {{ index + 1 }}</div>
            <div class="group-content">
              <el-input v-model="group.compName" placeholder="企业名称" clearable style="width: 300px" />
              <el-select v-model="group.roleId" placeholder="企业角色" clearable style="width: 180px">
                <el-option v-for="item in dicts.role" :key="item.id" :label="item.name" :value="item.id" />
              </el-select>
              <el-button type="danger" size="small" circle @click="removeCompanyGroup(index)" :disabled="searchForm.companyGroups.length === 1">
                <el-icon><Delete /></el-icon>
              </el-button>
            </div>
          </div>
          <el-button type="primary" size="small" @click="addCompanyGroup">
            <el-icon><Plus /></el-icon> 添加企业条件组
          </el-button>
        </div>

        <div class="form-actions">
          <el-button type="primary" size="large" @click="handleSearch" :loading="loading">
            <el-icon><Search /></el-icon> 查询
          </el-button>
          <el-button size="large" @click="handleReset">
            <el-icon><RefreshLeft /></el-icon> 重置
          </el-button>
        </div>
      </el-form>
    </div>

    <!-- 结果列表 -->
    <div class="result-panel">
      <div class="result-header">
        <span class="result-count" v-if="!loading">共 {{ pagination.total }} 条记录</span>
        <el-pagination
          v-if="pagination.total > 0"
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="pagination.total"
          @size-change="handleSearch"
          @current-change="handleSearch"
        />
      </div>

      <el-table :data="list" v-loading="loading" stripe style="width: 100%" class="result-table">
        <el-table-column prop="projectName" label="项目名称" min-width="250" show-overflow-tooltip />
        <el-table-column prop="money" label="金额（万元）" width="130" align="right">
          <template #default="{ row }">
            <span v-if="row.money !== null">{{ formatMoney(row.money) }}</span>
            <el-tag v-else size="small" type="info">未披露</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="date" label="日期" width="120" />
        <el-table-column prop="region" label="地区" width="120" show-overflow-tooltip />
        <el-table-column prop="ywlxName" label="业务类型" width="100" />
        <el-table-column label="招标类型" width="150">
          <template #default="{ row }">
            <el-tag v-for="(name, idx) in row.zblxNames" :key="idx" size="small" style="margin-right: 4px">{{ name }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="工程类型" width="150">
          <template #default="{ row }">
            <el-tag v-for="(name, idx) in row.gclxNames" :key="idx" size="small" type="success" style="margin-right: 4px">{{ name }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="cgfsName" label="采购方式" width="120" />
        <el-table-column prop="cgplName" label="采购品类" width="100" />
        <el-table-column label="企业信息" min-width="300">
          <template #default="{ row }">
            <div class="company-info">
              <div v-if="row.companies.owner.length" class="company-line">
                <span class="company-label">业主:</span>
                <span class="company-value">{{ row.companies.owner.join('、') }}</span>
              </div>
              <div v-if="row.companies.agent.length" class="company-line">
                <span class="company-label">代理:</span>
                <span class="company-value">{{ row.companies.agent.join('、') }}</span>
              </div>
              <div v-if="row.companies.bidder.length" class="company-line">
                <span class="company-label">投标:</span>
                <span class="company-value">{{ row.companies.bidder.join('、') }}</span>
              </div>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <el-empty v-if="!loading && list.length === 0" description="暂无数据，请调整查询条件后重试" />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Search, RefreshLeft, Plus, Delete } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import axios from 'axios';

const API_BASE = '/api';

// 字典数据
const dicts = reactive({
  ywlx: [],
  xxlx: [],
  role: [],
  cgfs: [],
  cgpl: [],
  zblx: [],
  gclx: [],
  province: [],
});

// 地区级联选项
const regionOptions = ref([]);

// 搜索表单
const searchForm = reactive({
  regions: [{ value: [] }],
  projectKeywords: '',
  ywlxIds: [],
  xxlxIds: [],
  dateRange: null,
  moneyMin: null,
  moneyMax: null,
  includeEmptyMoney: false,
  cgfsIds: [],
  cgplIds: [],
  zblxIds: [],
  gclxIds: [],
  companyGroups: [{ compName: '', roleId: null }],
});

// 结果列表
const list = ref([]);
const loading = ref(false);

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0,
});

// 加载字典
async function loadDicts() {
  try {
    const { data } = await axios.get(`${API_BASE}/trade/dicts`);
    if (data.code === 0) {
      Object.assign(dicts, data.data);
      // 构建级联选项
      regionOptions.value = (data.data.province || []).map(p => ({
        value: p.code,
        label: p.name,
        children: (p.cities || []).map(c => ({
          value: c,
          label: c,
        })),
      }));
    }
  } catch (e) {
    console.error('加载字典失败', e);
  }
}

// 地区操作
function addRegion() {
  searchForm.regions.push({ value: [] });
}
function removeRegion(index) {
  if (searchForm.regions.length > 1) {
    searchForm.regions.splice(index, 1);
  }
}

// 企业条件组操作
function addCompanyGroup() {
  searchForm.companyGroups.push({ compName: '', roleId: null });
}
function removeCompanyGroup(index) {
  if (searchForm.companyGroups.length > 1) {
    searchForm.companyGroups.splice(index, 1);
  }
}

// 格式化金额
function formatMoney(val) {
  if (val === null || val === undefined) return '-';
  if (val >= 100000000) {
    return (val / 100000000).toFixed(2) + '亿';
  }
  if (val >= 10000) {
    return (val / 10000).toFixed(2) + '万';
  }
  return val.toLocaleString('zh-CN', { maximumFractionDigits: 2 });
}

// 搜索
async function handleSearch() {
  loading.value = true;
  try {
    const params = buildParams();
    params.page = pagination.page;
    params.pageSize = pagination.pageSize;

    const { data } = await axios.post(`${API_BASE}/trade/search`, params);
    if (data.code === 0) {
      list.value = data.data.list || [];
      pagination.total = data.data.total || 0;
    } else {
      ElMessage.error(data.msg || '查询失败');
    }
  } catch (e) {
    ElMessage.error('网络错误，请稍后重试');
    console.error(e);
  } finally {
    loading.value = false;
  }
}

// 构建请求参数
function buildParams() {
  const params = {};

  // 地区
  const regions = searchForm.regions
    .filter(r => r.value && r.value.length)
    .map(r => {
      // value 是级联选择的数组，如 [['北京', '北京市'], ['河北', '石家庄']]
      const provs = new Set();
      const cityMap = {};
      r.value.forEach(path => {
        if (Array.isArray(path) && path.length >= 1) {
          const prov = path[0];
          provs.add(prov);
          if (path.length >= 2) {
            if (!cityMap[prov]) cityMap[prov] = [];
            cityMap[prov].push(path[1]);
          }
        }
      });
      // 如果有多个城市，拆分为多个region条件
      return Array.from(provs).map(prov => ({
        province: prov,
        cities: cityMap[prov] || [],
      }));
    });
  // 扁平化
  if (regions.length) {
    params.regions = regions.flat();
  }

  // 项目名称关键词
  if (searchForm.projectKeywords) {
    params.projectKeywords = searchForm.projectKeywords
      .split(/[\n,，;；]/)
      .map(s => s.trim())
      .filter(s => s);
  }

  // 业务类型
  if (searchForm.ywlxIds.length) {
    params.ywlxIds = searchForm.ywlxIds;
  }

  // 标讯类型
  if (searchForm.xxlxIds.length) {
    params.xxlxIds = searchForm.xxlxIds;
  }

  // 日期范围
  if (searchForm.dateRange && searchForm.dateRange.length === 2) {
    params.dateRange = {
      start: searchForm.dateRange[0],
      end: searchForm.dateRange[1],
    };
  }

  // 金额范围
  const moneyRange = {};
  if (searchForm.moneyMin !== null && searchForm.moneyMin !== '') {
    moneyRange.min = searchForm.moneyMin;
  }
  if (searchForm.moneyMax !== null && searchForm.moneyMax !== '') {
    moneyRange.max = searchForm.moneyMax;
  }
  if (Object.keys(moneyRange).length) {
    params.moneyRange = moneyRange;
  }
  if (searchForm.includeEmptyMoney) {
    params.includeEmptyMoney = true;
  }

  // 采购方式
  if (searchForm.cgfsIds.length) {
    params.cgfsIds = searchForm.cgfsIds;
  }

  // 采购品类
  if (searchForm.cgplIds.length) {
    params.cgplIds = searchForm.cgplIds;
  }

  // 招标类型
  if (searchForm.zblxIds.length) {
    params.zblxIds = searchForm.zblxIds;
  }

  // 工程类型
  if (searchForm.gclxIds.length) {
    params.gclxIds = searchForm.gclxIds;
  }

  // 企业条件组
  const companyGroups = searchForm.companyGroups
    .filter(g => g.compName || g.roleId)
    .map(g => ({
      compName: g.compName,
      roleId: g.roleId,
    }));
  if (companyGroups.length) {
    params.companyGroups = companyGroups;
  }

  return params;
}

// 重置
function handleReset() {
  searchForm.regions = [{ value: [] }];
  searchForm.projectKeywords = '';
  searchForm.ywlxIds = [];
  searchForm.xxlxIds = [];
  searchForm.dateRange = null;
  searchForm.moneyMin = null;
  searchForm.moneyMax = null;
  searchForm.includeEmptyMoney = false;
  searchForm.cgfsIds = [];
  searchForm.cgplIds = [];
  searchForm.zblxIds = [];
  searchForm.gclxIds = [];
  searchForm.companyGroups = [{ compName: '', roleId: null }];
  pagination.page = 1;
  list.value = [];
  pagination.total = 0;
}

onMounted(() => {
  loadDicts();
});
</script>

<style scoped>
.trade-search {
  max-width: 1600px;
  margin: 0 auto;
  padding: 20px;
}

.search-panel {
  background: #fff;
  border-radius: 8px;
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.panel-title {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 24px;
  color: #303133;
  border-bottom: 2px solid #409eff;
  padding-bottom: 12px;
}

.search-form :deep(.el-form-item__label) {
  font-weight: 600;
  color: #606266;
}

.form-row {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

.form-item-wide {
  flex: 1;
  min-width: 400px;
}

.region-selector {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.region-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.money-range {
  display: flex;
  align-items: center;
  gap: 8px;
}

.range-separator {
  color: #909399;
  font-size: 14px;
}

.company-section {
  background: #f8f9fa;
  border-radius: 6px;
  padding: 16px;
  margin-top: 16px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.section-header h3 {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}

.section-desc {
  font-size: 12px;
  color: #909399;
}

.company-group {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.group-label {
  font-size: 13px;
  color: #606266;
  font-weight: 500;
  min-width: 70px;
}

.group-content {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.form-actions {
  display: flex;
  gap: 16px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px dashed #dcdfe6;
}

.result-panel {
  background: #fff;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.result-count {
  font-size: 14px;
  color: #606266;
}

.result-table :deep(.el-table__cell) {
  padding: 12px 0;
}

.company-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.company-line {
  display: flex;
  gap: 4px;
  font-size: 13px;
  line-height: 1.4;
}

.company-label {
  color: #909399;
  min-width: 40px;
  flex-shrink: 0;
}

.company-value {
  color: #303133;
  word-break: break-all;
}
</style>
